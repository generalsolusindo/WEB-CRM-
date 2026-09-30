<?php

namespace Tests\Feature\Procurement;

use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsProcurementProject;
use Tests\TestCase;

/**
 * Regresi produksi: project yang ditandai Sales "butuh vendor luar" sejak Lead tidak
 * pernah bisa dikoreksi lagi lewat jalur mana pun (Lead terkunci begitu ada Procurement
 * Request), jadi Operasional tertahan selamanya menunggu deal vendor yang tidak pernah
 * akan datang begitu ternyata project itu dikerjakan tim sendiri.
 */
class CancelOutsideVendorNeedTest extends TestCase
{
    use BuildsProcurementProject;
    use RefreshDatabase;

    private function flaggedProject(): Project
    {
        // 'mixed' (bukan 'material_only') — vendor jasa luar & markReady cuma relevan
        // untuk order yang punya komponen jasa on-site.
        $project = $this->materialProject(orderType: 'mixed');
        $project->update(['needs_outside_vendor' => true]);

        return $project->fresh();
    }

    public function test_procurement_cancels_the_flag_and_operational_can_then_mark_ready(): void
    {
        $project = $this->flaggedProject();
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $operational = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $sales = User::find($project->salesOrder->quotation->sales_id);
        $technician = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $this->actingAs($operational)->put("/operational/projects/{$project->id}/technicians", [
            'technician_ids' => [$technician->id], 'leader_id' => $technician->id,
        ]);
        $this->settleProcurement($project);
        $this->actingAs($operational)->post("/operational/projects/{$project->id}/tasks", ['title' => 'Instalasi']);

        // Sebelum dibatalkan: Operasional masih tertahan, belum boleh menandai Siap.
        $this->assertFalse($operational->can('markReady', $project));
        $this->actingAs($operational)->post("/operational/projects/{$project->id}/ready")->assertForbidden();

        $this->actingAs($procurement)
            ->post("/procurement/project-procurements/{$project->id}/vendor-service/cancel-need")
            ->assertRedirect()
            ->assertSessionHas('success');

        $project->refresh();
        $this->assertFalse($project->needs_outside_vendor);
        $this->assertNull($project->vendorServicePayment);

        // Operasional & Sales diberi tahu.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $operational->id, 'type' => 'vendor_service.no_longer_needed', 'related_id' => $project->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $sales->id, 'type' => 'vendor_service.no_longer_needed', 'related_id' => $project->id,
        ]);

        // Sekarang Operasional bisa lanjut menandai Siap tanpa menunggu vendor.
        $this->assertTrue($operational->can('markReady', $project->fresh()));
        $this->actingAs($operational)->post("/operational/projects/{$project->id}/ready")->assertSessionHas('success');
    }

    public function test_cannot_cancel_when_no_longer_flagged(): void
    {
        $project = $this->materialProject();
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);

        $this->actingAs($procurement)
            ->post("/procurement/project-procurements/{$project->id}/vendor-service/cancel-need")
            ->assertSessionHasErrors('needs_outside_vendor');

        $this->assertFalse($project->fresh()->needs_outside_vendor);
    }

    public function test_cannot_cancel_once_a_vendor_deal_already_exists(): void
    {
        $project = $this->flaggedProject();
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $vendor = Vendor::create(['name' => 'Vendor Jasa', 'provides_technical' => true]);

        $this->actingAs($procurement)->put("/procurement/project-procurements/{$project->id}/vendor-service", [
            'vendor_id' => $vendor->id, 'total_fee' => 5000000, 'terms' => 'pay_at_end',
            'bank_name' => 'BCA', 'account_number' => '123', 'account_holder' => 'PT Vendor',
        ])->assertRedirect();

        $this->actingAs($procurement)
            ->post("/procurement/project-procurements/{$project->id}/vendor-service/cancel-need")
            ->assertSessionHasErrors('needs_outside_vendor');

        $this->assertTrue($project->fresh()->needs_outside_vendor);
    }

    public function test_only_procurement_can_cancel(): void
    {
        $project = $this->flaggedProject();

        foreach (['sales', 'operational', 'management', 'finance'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]))
                ->post("/procurement/project-procurements/{$project->id}/vendor-service/cancel-need")
                ->assertForbidden();
        }

        $this->assertTrue($project->fresh()->needs_outside_vendor);
    }
}
