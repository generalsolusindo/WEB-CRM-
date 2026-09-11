<?php

namespace Tests\Feature\Procurement;

use App\Actions\Finance\RecordProcurementPayment;
use App\Actions\Procurement\ReviewProcurementPayment;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsProcurementProject;
use Tests\TestCase;

class ProjectProcurementScreenTest extends TestCase
{
    use BuildsProcurementProject;
    use RefreshDatabase;

    public function test_index_lists_projects_for_procurement(): void
    {
        $project = $this->materialProject();
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);

        $this->actingAs($procurement)->get('/procurement/project-procurements')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Procurement/ProjectProcurements/Index')
                ->has('projects', 1));
    }

    public function test_show_and_submit_and_confirm_flow_over_http(): void
    {
        $project = $this->materialProject();
        $vendor = Vendor::create(['name' => 'PT Kabel']);
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $pm = User::find($project->delegated_to);
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);

        $this->actingAs($procurement)->get("/procurement/project-procurements/{$project->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Procurement/ProjectProcurements/Show')->where('editable', true));

        $lines = $project->actualProcurements->map(fn ($it) => [
            'id' => $it->id, 'from_office_stock' => false, 'vendor_id' => $vendor->id, 'cost_price' => 300000,
        ])->all();

        $this->actingAs($procurement)
            ->post("/procurement/project-procurements/{$project->id}/submit", ['pricing_mode' => 'itemized', 'lines' => $lines])
            ->assertSessionHas('success');

        $payment = $project->fresh()->procurementPayment;
        $this->assertSame('pending_pm', $payment->status->value);

        // Sourcing terkunci
        $this->actingAs($procurement)
            ->put("/procurement/project-procurements/{$project->id}/sourcing", ['pricing_mode' => 'itemized', 'lines' => $lines])
            ->assertStatus(409);

        app(ReviewProcurementPayment::class)->handle($payment, $pm, true, null);
        app(RecordProcurementPayment::class)->handle($payment->fresh(), $finance, [
            'item_ids' => $project->actualProcurements()->pluck('id')->all(),
            'proof' => \Illuminate\Http\UploadedFile::fake()->create('tf.pdf', 20, 'application/pdf'),
        ]);

        $this->actingAs($procurement)->post("/procurement/project-procurements/{$project->id}/confirm")
            ->assertSessionHas('success');
        $this->assertSame('confirmed', $payment->fresh()->status->value);

        $item = $project->actualProcurements()->firstOrFail();
        $this->actingAs($procurement)->post("/procurement/project-procurements/items/{$item->id}/receive")
            ->assertSessionHas('success');
        $this->assertSame('received', $item->fresh()->status);
    }

    public function test_non_procurement_cannot_open_show(): void
    {
        $project = $this->materialProject();
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);

        $this->actingAs($ops)->get("/procurement/project-procurements/{$project->id}")->assertForbidden();
    }

    public function test_finance_pay_over_http_requires_proof_file(): void
    {
        $project = $this->materialProject();
        $vendor = Vendor::create(['name' => 'PT V']);
        $project->actualProcurements()->update(['vendor_id' => $vendor->id, 'cost_price' => 100000]);
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $pm = User::find($project->delegated_to);
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);

        $payment = app(\App\Actions\Procurement\SubmitProcurementPayment::class)
            ->handle($project->fresh(), $procurement, ['pricing_mode' => 'itemized']);
        app(ReviewProcurementPayment::class)->handle($payment, $pm, true, null);

        $ids = $project->actualProcurements()->pluck('id')->all();

        $this->actingAs($finance)->post("/finance/procurement-payments/{$payment->id}/pay", ['item_ids' => $ids])
            ->assertSessionHasErrors('proof');

        $this->actingAs($finance)->post("/finance/procurement-payments/{$payment->id}/pay", [
            'item_ids' => $ids,
            'proof' => \Illuminate\Http\UploadedFile::fake()->create('tf.pdf', 20, 'application/pdf'),
        ])->assertSessionHas('success');
    }

    public function test_receive_all_and_payment_history(): void
    {
        $project = $this->materialProject([
            ['item_name' => 'A', 'qty' => 1, 'unit' => 'unit', 'cost_price' => 100000],
            ['item_name' => 'B', 'qty' => 1, 'unit' => 'unit', 'cost_price' => 200000],
        ]);
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $pm = User::find($project->delegated_to);
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $vendor = Vendor::create(['name' => 'PT V']);
        $project->actualProcurements()->update(['vendor_id' => $vendor->id, 'cost_price' => 100000]);

        $payment = app(\App\Actions\Procurement\SubmitProcurementPayment::class)
            ->handle($project->fresh(), $procurement, ['pricing_mode' => 'itemized']);
        app(ReviewProcurementPayment::class)->handle($payment, $pm, true, null);
        app(RecordProcurementPayment::class)->handle($payment->fresh(), $finance, [
            'item_ids' => $project->actualProcurements()->pluck('id')->all(),
            'proof' => \Illuminate\Http\UploadedFile::fake()->create('tf.pdf', 20, 'application/pdf'),
        ]);
        app(\App\Actions\Procurement\ConfirmProcurementPayment::class)->handle($payment->fresh(), $procurement);

        // terima semua sekaligus
        $this->actingAs($procurement)->post("/procurement/project-procurements/{$project->id}/receive-all")
            ->assertSessionHas('success');
        $this->assertTrue($project->actualProcurements()->get()->every(fn ($i) => $i->status === 'received'));

        // riwayat pengajuan bisa dibuka Procurement
        $this->actingAs($procurement)->get("/procurement/procurement-payments/{$payment->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ProcurementPayments/View/Show')->where('payment.number', $payment->number));
    }
}
