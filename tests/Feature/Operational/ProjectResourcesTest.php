<?php

namespace Tests\Feature\Operational;

use App\Actions\Finance\RecordProcurementPayment;
use App\Actions\Procurement\ReviewProcurementPayment;
use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsProcurementProject;
use Tests\TestCase;

class ProjectResourcesTest extends TestCase
{
    use BuildsProcurementProject;
    use RefreshDatabase;

    private User $ops;
    private User $procurement;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $this->procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
    }

    private function sourcedPayload(Project $project, Vendor $vendor, float $cost = 450000): array
    {
        return [
            'pricing_mode' => 'itemized',
            'lines' => $project->actualProcurements->map(fn ($item) => [
                'id' => $item->id,
                'from_office_stock' => false,
                'vendor_id' => $vendor->id,
                'cost_price' => $cost,
            ])->all(),
        ];
    }

    public function test_procurement_submits_sourcing_operational_cannot(): void
    {
        $project = $this->materialProject();
        $vendor = Vendor::create(['name' => 'V']);

        $this->actingAs($this->ops)
            ->post("/procurement/project-procurements/{$project->id}/submit", $this->sourcedPayload($project, $vendor))
            ->assertForbidden();

        $this->actingAs($this->procurement)
            ->post("/procurement/project-procurements/{$project->id}/submit", $this->sourcedPayload($project, $vendor))
            ->assertSessionHas('success');

        $item = $project->actualProcurements()->firstOrFail();
        $this->assertSame($vendor->id, $item->vendor_id);
        $this->assertSame('450000.00', $item->cost_price);
        $this->assertSame('pending_pm', $project->fresh()->procurementPayment->status->value);
    }

    public function test_project_moves_to_waiting_resource_when_finance_pays(): void
    {
        $project = $this->materialProject();
        $vendor = Vendor::create(['name' => 'V']);
        $pm = User::find($project->delegated_to);
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);

        $this->actingAs($this->procurement)
            ->post("/procurement/project-procurements/{$project->id}/submit", $this->sourcedPayload($project, $vendor));
        $payment = $project->fresh()->procurementPayment;
        app(ReviewProcurementPayment::class)->handle($payment, $pm, true, null);

        $this->assertSame('planning', $project->fresh()->status);

        app(RecordProcurementPayment::class)->handle($payment->fresh(), $finance, [
            'item_ids' => $project->actualProcurements()->pluck('id')->all(),
            'proof' => \Illuminate\Http\UploadedFile::fake()->create('tf.pdf', 20, 'application/pdf'),
        ]);

        $this->assertSame('waiting_resource', $project->fresh()->status);
    }

    public function test_all_received_notifies_operational(): void
    {
        $project = $this->materialProject();
        $this->settleProcurement($project);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->ops->id,
            'type' => 'project_procurement.ready',
        ]);
    }

    public function test_all_office_stock_request_skips_finance_and_completes_on_pm_approval(): void
    {
        $project = $this->materialProject([
            ['item_name' => 'Router', 'qty' => 1, 'unit' => 'unit', 'cost_price' => 900000],
        ]);
        $item = $project->actualProcurements()->firstOrFail();
        $pm = User::find($project->delegated_to);

        $this->actingAs($this->procurement)->post("/procurement/project-procurements/{$project->id}/submit", [
            'pricing_mode' => 'itemized',
            'lines' => [[
                'id' => $item->id,
                'from_office_stock' => true,
                'office_stock_note' => 'ambil dari gudang',
            ]],
        ])->assertSessionHas('success');

        $item->refresh();
        $this->assertTrue($item->from_office_stock);
        $this->assertSame('pending', $item->status);

        $payment = $project->fresh()->procurementPayment;
        app(ReviewProcurementPayment::class)->handle($payment, $pm, true, null);

        $this->assertSame('confirmed', $payment->fresh()->status->value);
        $this->assertSame('received', $item->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->ops->id,
            'type' => 'project_procurement.ready',
        ]);
    }

    public function test_operational_adds_and_deletes_extra_item_only_while_pending(): void
    {
        $project = $this->materialProject();

        $this->actingAs($this->ops)->post("/operational/projects/{$project->id}/actual-procurements", [
            'item_name' => 'Bracket tambahan', 'qty' => 4, 'unit' => 'pcs', 'cost_price' => 25000,
        ])->assertSessionHas('success');

        $extra = $project->actualProcurements()->where('item_name', 'Bracket tambahan')->firstOrFail();
        $this->assertSame($this->ops->id, $extra->requested_by);

        $this->actingAs($this->ops)->delete("/operational/projects/{$project->id}/actual-procurements/{$extra->id}")
            ->assertSessionHas('success');

        // item yang sudah masuk pengajuan pembayaran tak bisa dihapus
        $vendor = \App\Models\Vendor::create(['name' => 'V']);
        $project->actualProcurements()->update(['vendor_id' => $vendor->id, 'cost_price' => 1000]);
        app(\App\Actions\Procurement\SubmitProcurementPayment::class)
            ->handle($project->fresh(), $this->procurement, ['pricing_mode' => 'itemized']);
        $seed = $project->actualProcurements()->firstOrFail();
        $this->assertNotNull($seed->fresh()->procurement_payment_id);
        $this->actingAs($this->ops)->delete("/operational/projects/{$project->id}/actual-procurements/{$seed->id}")
            ->assertSessionHas('error');
    }

    public function test_assign_technicians_requires_exactly_one_leader(): void
    {
        $project = $this->materialProject();
        $t1 = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $t2 = User::factory()->create(['role' => 'technician', 'is_active' => true]);

        $this->actingAs($this->ops)->put("/operational/projects/{$project->id}/technicians", [
            'technician_ids' => [$t1->id], 'leader_id' => $t2->id,
        ])->assertSessionHasErrors('leader_id');

        $this->actingAs($this->ops)->put("/operational/projects/{$project->id}/technicians", [
            'technician_ids' => [$t1->id, $t2->id], 'leader_id' => $t1->id,
        ])->assertSessionHas('success');
        $this->assertSame(1, $project->technicians()->where('is_leader', true)->count());
    }

    public function test_mark_ready_needs_all_items_received_plus_task_and_leader(): void
    {
        $project = $this->materialProject();
        $tech = User::factory()->create(['role' => 'technician', 'is_active' => true]);

        $this->actingAs($this->ops)->post("/operational/projects/{$project->id}/tasks", ['title' => 'Instalasi']);
        $this->actingAs($this->ops)->put("/operational/projects/{$project->id}/technicians", [
            'technician_ids' => [$tech->id], 'leader_id' => $tech->id,
        ]);

        // barang belum diterima
        $this->actingAs($this->ops)->post("/operational/projects/{$project->id}/ready")
            ->assertSessionHasErrors('project');

        $this->settleProcurement($project);
        $this->actingAs($this->ops)->post("/operational/projects/{$project->id}/ready")
            ->assertSessionHas('success');
        $this->assertSame('ready', $project->fresh()->status);
    }

    public function test_pure_service_project_can_be_ready_without_procurement(): void
    {
        $project = $this->materialProject();
        $project->actualProcurements()->delete(); // murni jasa
        $tech = User::factory()->create(['role' => 'technician', 'is_active' => true]);

        $this->actingAs($this->ops)->post("/operational/projects/{$project->id}/tasks", ['title' => 'Instalasi']);
        $this->actingAs($this->ops)->put("/operational/projects/{$project->id}/technicians", [
            'technician_ids' => [$tech->id], 'leader_id' => $tech->id,
        ]);

        $this->actingAs($this->ops)->post("/operational/projects/{$project->id}/ready")
            ->assertSessionHas('success');
        $this->assertSame('ready', $project->fresh()->status);
    }
}
