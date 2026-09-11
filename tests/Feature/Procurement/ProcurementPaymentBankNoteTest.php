<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsProcurementProject;
use Tests\TestCase;

class ProcurementPaymentBankNoteTest extends TestCase
{
    use BuildsProcurementProject;
    use RefreshDatabase;

    public function test_itemized_submit_stores_bank_account_note_per_item(): void
    {
        $project = $this->materialProject();
        $vendor = Vendor::create(['name' => 'PT Rekening', 'bank_account_note' => 'BCA 111 a.n. PT Rekening']);
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $item = $project->actualProcurements()->firstOrFail();

        $this->actingAs($procurement)->post("/procurement/project-procurements/{$project->id}/submit", [
            'pricing_mode' => 'itemized',
            'lines' => [[
                'id' => $item->id,
                'from_office_stock' => false,
                'vendor_id' => $vendor->id,
                'cost_price' => 300000,
                'bank_account_note' => 'BCA 111 a.n. PT Rekening',
            ]],
        ])->assertSessionHas('success');

        $this->assertSame('BCA 111 a.n. PT Rekening', $item->fresh()->bank_account_note);
    }

    public function test_office_stock_item_never_stores_bank_account_note(): void
    {
        $project = $this->materialProject();
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $item = $project->actualProcurements()->firstOrFail();

        $this->actingAs($procurement)->post("/procurement/project-procurements/{$project->id}/submit", [
            'pricing_mode' => 'itemized',
            'lines' => [[
                'id' => $item->id,
                'from_office_stock' => true,
                'office_stock_note' => 'stok gudang',
                'bank_account_note' => 'coba diselundupkan',
            ]],
        ])->assertSessionHas('success');

        $this->assertNull($item->fresh()->bank_account_note);
    }

    public function test_lump_sum_submit_stores_bank_account_note_on_payment(): void
    {
        $project = $this->materialProject();
        $vendor = Vendor::create(['name' => 'PT Borongan', 'bank_account_note' => 'Mandiri 222 a.n. PT Borongan']);
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);

        $this->actingAs($procurement)->post("/procurement/project-procurements/{$project->id}/submit", [
            'pricing_mode' => 'lump_sum',
            'lump_sum_vendor_id' => $vendor->id,
            'lump_sum_amount' => 2000000,
            'bank_account_note' => 'Mandiri 222 a.n. PT Borongan',
            'lines' => $project->actualProcurements->map(fn ($it) => ['id' => $it->id, 'from_office_stock' => false])->all(),
        ])->assertSessionHas('success');

        $payment = $project->fresh()->procurementPayment;
        $this->assertSame('Mandiri 222 a.n. PT Borongan', $payment->bank_account_note);
    }

    public function test_pm_and_finance_screens_receive_bank_account_note(): void
    {
        $project = $this->materialProject();
        $vendor = Vendor::create(['name' => 'PT Rekening', 'bank_account_note' => 'BCA 111 a.n. PT Rekening']);
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $pm = User::find($project->delegated_to);
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $item = $project->actualProcurements()->firstOrFail();

        $this->actingAs($procurement)->post("/procurement/project-procurements/{$project->id}/submit", [
            'pricing_mode' => 'itemized',
            'lines' => [[
                'id' => $item->id, 'from_office_stock' => false, 'vendor_id' => $vendor->id,
                'cost_price' => 300000, 'bank_account_note' => 'BCA 111 a.n. PT Rekening',
            ]],
        ]);
        $payment = $project->fresh()->procurementPayment;

        $this->actingAs($pm)->get("/project-manager/procurement-payments/{$payment->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('payment.items.0.bank_account_note', 'BCA 111 a.n. PT Rekening'));

        app(\App\Actions\Procurement\ReviewProcurementPayment::class)->handle($payment, $pm, true, null);

        $this->actingAs($finance)->get("/finance/procurement-payments/{$payment->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('payment.items.0.bank_account_note', 'BCA 111 a.n. PT Rekening'));
    }
}
