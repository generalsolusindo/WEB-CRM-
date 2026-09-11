<?php

namespace Tests\Feature\Finance;

use App\Actions\Procurement\ReviewProcurementPayment;
use App\Actions\Procurement\SubmitProcurementPayment;
use App\Models\ProcurementPayment;
use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsProcurementProject;
use Tests\TestCase;

class ProcurementPaymentPayTest extends TestCase
{
    use BuildsProcurementProject;
    use RefreshDatabase;

    private function approved(Project $project): ProcurementPayment
    {
        $vendor = Vendor::create(['name' => 'PT V']);
        $project->actualProcurements()->update(['vendor_id' => $vendor->id, 'cost_price' => 400000]);
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $pm = User::find($project->delegated_to);

        $payment = app(SubmitProcurementPayment::class)->handle($project->fresh(), $procurement, ['pricing_mode' => 'itemized']);
        app(ReviewProcurementPayment::class)->handle($payment, $pm, true, null);

        return $payment->fresh();
    }

    public function test_finance_index_and_show(): void
    {
        $project = $this->materialProject();
        $payment = $this->approved($project);
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);

        $this->actingAs($finance)->get('/finance/procurement-payments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ProcurementPayments/Pay/Index')->has('payments', 1));

        $this->actingAs($finance)->get("/finance/procurement-payments/{$payment->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ProcurementPayments/Pay/Show')->where('canPay', true));
    }

    public function test_finance_records_payment_partially_then_fully(): void
    {
        $project = $this->materialProject([
            ['item_name' => 'Router', 'qty' => 1, 'unit' => 'unit', 'cost_price' => 800000],
            ['item_name' => 'Switch', 'qty' => 1, 'unit' => 'unit', 'cost_price' => 500000],
        ]);
        $payment = $this->approved($project);
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $items = $project->actualProcurements()->orderBy('id')->pluck('id')->all();

        // bayar satu item dulu
        $this->actingAs($finance)->post("/finance/procurement-payments/{$payment->id}/pay", [
            'item_ids' => [$items[0]],
            'proof' => UploadedFile::fake()->create('tf1.pdf', 30, 'application/pdf'),
            'proof_scope' => 'items',
        ])->assertSessionHas('success');

        $this->assertSame('approved_pm', $payment->fresh()->status->value);
        $this->assertTrue($project->actualProcurements()->find($items[0])->is_paid);
        $this->assertDatabaseHas('procurement_payment_proofs', ['actual_procurement_id' => $items[0]]);

        // bayar sisanya → status paid
        $this->actingAs($finance)->post("/finance/procurement-payments/{$payment->id}/pay", [
            'item_ids' => [$items[1]],
            'proof' => UploadedFile::fake()->create('tf2.pdf', 30, 'application/pdf'),
            'proof_scope' => 'all',
        ])->assertSessionHas('success');

        $this->assertSame('paid', $payment->fresh()->status->value);
        $this->assertDatabaseHas('notifications', ['type' => 'procurement_payment.paid']);
    }

    public function test_non_finance_cannot_pay(): void
    {
        $project = $this->materialProject();
        $payment = $this->approved($project);
        $pm = User::find($project->delegated_to);

        $this->actingAs($pm)->post("/finance/procurement-payments/{$payment->id}/pay", [
            'item_ids' => $project->actualProcurements()->pluck('id')->all(),
        ])->assertForbidden();
    }
}
