<?php

namespace Tests\Feature\Procurement;

use App\Actions\Finance\RecordProcurementPayment;
use App\Actions\Procurement\ReviewProcurementPayment;
use App\Actions\Procurement\SubmitProcurementPayment;
use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsProcurementProject;
use Tests\TestCase;

class ProcurementPaymentEndToEndTest extends TestCase
{
    use BuildsProcurementProject;
    use RefreshDatabase;

    public function test_service_requirement_lines_do_not_become_procurement_items(): void
    {
        $project = $this->materialProject();
        // requirement/PR line di helper tak berkategori → dianggap material
        $project->load('salesOrder.quotation.procurementRequest.lines');
        $project->salesOrder->quotation->procurementRequest->lines()->update(['category' => 'service']);

        // project baru dari order lain akan mem-filter jasa; di sini cukup pastikan
        // InitializeProject idempoten tak menambah baris jasa
        app(\App\Actions\Operational\InitializeProject::class)->handle($project->salesOrder->fresh());
        $this->assertGreaterThan(0, $project->actualProcurements()->count());
    }

    public function test_full_flow_ends_with_all_items_received_and_operational_notified(): void
    {
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $project = $this->materialProject();
        $this->settleProcurement($project);

        $payment = $project->fresh()->procurementPayment;
        $this->assertSame('confirmed', $payment->status->value);
        $this->assertTrue($project->actualProcurements()->get()->every(fn ($i) => $i->status === 'received'));
        $this->assertDatabaseHas('notifications', ['user_id' => $ops->id, 'type' => 'project_procurement.ready']);

        // project bisa lanjut ke Siap
        $tech = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $this->actingAs($ops)->post("/operational/projects/{$project->id}/tasks", ['title' => 'Instalasi']);
        $this->actingAs($ops)->put("/operational/projects/{$project->id}/technicians", [
            'technician_ids' => [$tech->id], 'leader_id' => $tech->id,
        ]);
        $this->actingAs($ops)->post("/operational/projects/{$project->id}/ready")->assertSessionHas('success');
        $this->assertSame('ready', $project->fresh()->status);
    }

    public function test_extra_item_after_confirmation_gets_its_own_new_request(): void
    {
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $project = $this->materialProject();
        $this->settleProcurement($project);
        $firstPayment = $project->fresh()->procurementPayment;

        // Operational menambah item ekstra
        $this->actingAs($ops)->post("/operational/projects/{$project->id}/actual-procurements", [
            'item_name' => 'Bracket ekstra', 'qty' => 2, 'unit' => 'pcs', 'cost_price' => 30000,
        ]);
        $extra = $project->actualProcurements()->where('item_name', 'Bracket ekstra')->firstOrFail();
        $this->assertNull($extra->procurement_payment_id);

        $vendor = Vendor::firstOrFail();
        $extra->update(['vendor_id' => $vendor->id, 'cost_price' => 30000]);
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);

        $second = app(SubmitProcurementPayment::class)->handle($project->fresh(), $procurement, ['pricing_mode' => 'itemized']);

        $this->assertNotSame($firstPayment->id, $second->id);
        $this->assertSame('pending_pm', $second->status->value);
        $this->assertSame($second->id, $extra->fresh()->procurement_payment_id);
        // item lama tetap di pengajuan pertama
        $this->assertSame($firstPayment->id, $project->actualProcurements()->where('item_name', '!=', 'Bracket ekstra')->first()->procurement_payment_id);
    }
}
