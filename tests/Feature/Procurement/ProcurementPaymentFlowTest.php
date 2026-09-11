<?php

namespace Tests\Feature\Procurement;

use App\Actions\Finance\RecordProcurementPayment;
use App\Actions\Procurement\ConfirmProcurementPayment;
use App\Actions\Procurement\ReviewProcurementPayment;
use App\Actions\Procurement\SubmitProcurementPayment;
use App\Models\ProcurementPayment;
use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsProcurementProject;
use Tests\TestCase;

class ProcurementPaymentFlowTest extends TestCase
{
    use BuildsProcurementProject;
    use RefreshDatabase;

    private function source(Project $project, float $cost = 500000): Vendor
    {
        $vendor = Vendor::create(['name' => 'PT Vendor']);
        $project->actualProcurements()->update(['vendor_id' => $vendor->id, 'cost_price' => $cost]);

        return $vendor;
    }

    public function test_full_happy_path_procurement_pm_finance_procurement(): void
    {
        $project = $this->materialProject();
        $this->source($project);

        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $pm = User::find($project->delegated_to);
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);

        $payment = app(SubmitProcurementPayment::class)->handle($project, $procurement, ['pricing_mode' => 'itemized']);
        $this->assertSame('pending_pm', $payment->status->value);
        $this->assertMatchesRegularExpression('#^1/GS-PP/\d{2}/\d{4}$#', $payment->number);
        $this->assertDatabaseHas('notifications', ['user_id' => $pm->id, 'type' => 'procurement_payment.pending_pm']);

        app(ReviewProcurementPayment::class)->handle($payment, $pm, true, null);
        $payment->refresh();
        $this->assertSame('approved_pm', $payment->status->value);
        $this->assertDatabaseHas('notifications', ['user_id' => $finance->id, 'type' => 'procurement_payment.approved_pm']);

        $itemIds = $project->actualProcurements()->pluck('id')->all();
        app(RecordProcurementPayment::class)->handle($payment, $finance, [
            'item_ids' => $itemIds,
            'proof' => UploadedFile::fake()->create('tf.pdf', 40, 'application/pdf'),
            'proof_scope' => 'all',
        ]);
        $payment->refresh();
        $this->assertSame('paid', $payment->status->value);
        $this->assertTrue($project->actualProcurements()->firstOrFail()->is_paid);
        $this->assertSame('purchased', $project->actualProcurements()->firstOrFail()->status);
        $this->assertDatabaseHas('procurement_payment_proofs', ['procurement_payment_id' => $payment->id, 'actual_procurement_id' => null]);
        $this->assertDatabaseHas('notifications', ['user_id' => $procurement->id, 'type' => 'procurement_payment.paid']);

        app(ConfirmProcurementPayment::class)->handle($payment, $procurement);
        $this->assertSame('confirmed', $payment->fresh()->status->value);
    }

    public function test_pm_reject_returns_to_procurement_and_can_resubmit(): void
    {
        $project = $this->materialProject();
        $this->source($project);
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $pm = User::find($project->delegated_to);

        $payment = app(SubmitProcurementPayment::class)->handle($project, $procurement, ['pricing_mode' => 'itemized']);

        $this->expectException(ValidationException::class);
        try {
            app(ReviewProcurementPayment::class)->handle($payment, $pm, false, '');
        } finally {
            app(ReviewProcurementPayment::class)->handle($payment, $pm, false, 'Harga kabel kemahalan');
            $payment->refresh();
            $this->assertSame('rejected_pm', $payment->status->value);
            $this->assertTrue($payment->status->isEditable());
            $this->assertDatabaseHas('notifications', ['user_id' => $procurement->id, 'type' => 'procurement_payment.rejected_pm']);

            // bisa diajukan ulang
            $again = app(SubmitProcurementPayment::class)->handle($project->fresh(), $procurement, ['pricing_mode' => 'itemized']);
            $this->assertSame($payment->id, $again->id);
            $this->assertSame('pending_pm', $again->status->value);
        }
    }

    public function test_submit_blocks_when_item_missing_vendor_or_price(): void
    {
        $project = $this->materialProject();
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);

        $this->expectException(ValidationException::class);
        app(SubmitProcurementPayment::class)->handle($project, $procurement, ['pricing_mode' => 'itemized']);
    }

    public function test_office_stock_item_skips_payment_and_is_received_on_submit(): void
    {
        $project = $this->materialProject([
            ['item_name' => 'Router', 'qty' => 1, 'unit' => 'unit', 'cost_price' => 1000000],
            ['item_name' => 'Bracket', 'qty' => 4, 'unit' => 'pcs', 'cost_price' => 20000],
        ]);
        $vendor = Vendor::create(['name' => 'PT Vendor']);
        $items = $project->actualProcurements()->orderBy('id')->get();
        $items[0]->update(['vendor_id' => $vendor->id, 'cost_price' => 900000]);
        $items[1]->update(['from_office_stock' => true, 'office_stock_note' => 'sisa gudang']);

        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $pm = User::find($project->delegated_to);
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);

        $payment = app(SubmitProcurementPayment::class)->handle($project, $procurement, ['pricing_mode' => 'itemized']);
        app(ReviewProcurementPayment::class)->handle($payment, $pm, true, null);

        $bracket = $items[1]->fresh();
        $this->assertSame('received', $bracket->status);
        $this->assertTrue($bracket->is_paid);

        // Finance hanya bayar item yang dibeli; pengajuan langsung 'paid'.
        app(RecordProcurementPayment::class)->handle($payment, $finance, [
            'item_ids' => [$items[0]->id, $items[1]->id],
            'proof' => UploadedFile::fake()->create('tf.pdf', 20, 'application/pdf'),
        ]);
        $this->assertSame('paid', $payment->fresh()->status->value);
    }

    public function test_lump_sum_mode_requires_vendor_and_amount(): void
    {
        $project = $this->materialProject();
        $this->source($project);
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);

        $this->expectException(ValidationException::class);
        app(SubmitProcurementPayment::class)->handle($project, $procurement, ['pricing_mode' => 'lump_sum']);
    }
}
