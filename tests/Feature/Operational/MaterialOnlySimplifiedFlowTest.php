<?php

namespace Tests\Feature\Operational;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsProcurementProject;
use Tests\TestCase;

/**
 * Project Material Only: Operational tidak lagi lewat teknisi/task/BAST sama
 * sekali — cukup terima barang dari Procurement lalu Delivery Note.
 */
class MaterialOnlySimplifiedFlowTest extends TestCase
{
    use BuildsProcurementProject;
    use RefreshDatabase;

    public function test_technician_team_task_and_mark_ready_are_all_blocked_for_material_only(): void
    {
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $project = $this->materialProject();
        $this->settleProcurement($project);
        $project->refresh();

        $tech = User::factory()->create(['role' => 'technician', 'is_active' => true]);

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/technicians", [
            'technician_ids' => [$tech->id], 'leader_id' => $tech->id,
        ])->assertForbidden();

        $this->actingAs($ops)->post("/operational/projects/{$project->id}/tasks", ['title' => 'Instalasi'])
            ->assertForbidden();

        $this->actingAs($ops)->post("/operational/projects/{$project->id}/ready")->assertForbidden();
    }

    public function test_complete_direct_requires_full_material_delivery_not_just_procurement_received(): void
    {
        Storage::fake('local');
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $project = $this->materialProject([
            ['item_name' => 'Router', 'qty' => 2, 'unit' => 'unit', 'cost_price' => 500000],
            ['item_name' => 'Switch', 'qty' => 3, 'unit' => 'unit', 'cost_price' => 300000],
        ]);
        $this->settleProcurement($project);
        $project->refresh();
        $this->assertSame('waiting_resource', $project->status);

        // Barang sudah diterima procurement, tapi belum ada Delivery Note sama sekali.
        $this->actingAs($ops)->post("/operational/projects/{$project->id}/complete")->assertForbidden();

        $so = $project->salesOrder;
        $router = $so->lines->firstWhere('item_name', 'Router');
        $switch = $so->lines->firstWhere('item_name', 'Switch');

        // Kirim sebagian saja dulu — masih belum boleh selesai.
        $this->actingAs($ops)->post("/operational/sales-orders/{$so->id}/delivery-notes", [
            'delivery_method' => 'sendiri',
            'delivery_address' => 'Site A',
            'dispatch_proof' => UploadedFile::fake()->image('bukti1.jpg'),
            'lines' => [['sales_order_line_id' => $router->id, 'qty_delivered' => 2]],
        ]);
        $this->actingAs($ops)->post("/operational/projects/{$project->id}/complete")->assertForbidden();

        // Kirim sisanya — sekarang baru boleh selesai.
        $this->actingAs($ops)->post("/operational/sales-orders/{$so->id}/delivery-notes", [
            'delivery_method' => 'ekspedisi',
            'delivery_address' => 'Site A',
            'shipper_name' => 'JNE',
            'tracking_number' => 'JNE001',
            'dispatch_proof' => UploadedFile::fake()->image('bukti2.jpg'),
            'lines' => [['sales_order_line_id' => $switch->id, 'qty_delivered' => 3]],
        ]);

        $this->actingAs($ops)->post("/operational/projects/{$project->id}/complete")
            ->assertSessionHas('success');
        $this->assertSame('completed', $project->fresh()->status);
    }

    public function test_mixed_order_still_uses_full_workflow_unaffected(): void
    {
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $project = $this->materialProject([], 'mixed');
        $this->settleProcurement($project);
        $project->refresh();

        $tech = User::factory()->create(['role' => 'technician', 'is_active' => true]);

        $this->actingAs($ops)->post("/operational/projects/{$project->id}/tasks", ['title' => 'Instalasi'])
            ->assertSessionHas('success');
        $this->actingAs($ops)->put("/operational/projects/{$project->id}/technicians", [
            'technician_ids' => [$tech->id], 'leader_id' => $tech->id,
        ])->assertSessionHas('success');

        // completeDirect tidak berlaku untuk mixed.
        $this->actingAs($ops)->post("/operational/projects/{$project->id}/complete")->assertForbidden();
    }
}
