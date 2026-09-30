<?php

namespace Tests\Feature\Operational;

use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsProcurementProject;
use Tests\TestCase;

/**
 * Kebalikan dari CancelOutsideVendorNeedTest: project jasa murni (tanpa kebutuhan
 * barang) yang tadinya disangka cukup tim sendiri (needs_outside_vendor=false) tidak
 * pernah muncul di daftar Procurement, jadi vendor tidak akan pernah bisa dipasang dan
 * SOW tidak akan pernah bisa dibuat — sampai Operasional menandainya lewat aksi ini.
 */
class FlagOutsideVendorNeedTest extends TestCase
{
    use BuildsProcurementProject;
    use RefreshDatabase;

    private function serviceOnlyInternalProject(): Project
    {
        $project = $this->materialProject(orderType: 'mixed');
        $project->actualProcurements()->delete();

        return $project->fresh();
    }

    public function test_operational_flags_it_and_procurement_can_then_add_a_vendor_and_unlock_sow(): void
    {
        $project = $this->serviceOnlyInternalProject();
        $operational = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $technician = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $this->actingAs($operational)->put("/operational/projects/{$project->id}/technicians", [
            'technician_ids' => [$technician->id], 'leader_id' => $technician->id,
        ]);

        // Sebelum ditandai: tidak muncul di daftar Procurement sama sekali, dan SOW belum bisa.
        $this->actingAs($procurement)->get('/procurement/project-procurements')
            ->assertInertia(fn ($page) => $page->has('projects', 0));
        $this->assertFalse($operational->can('viewSow', $project));

        $this->actingAs($operational)->post("/operational/projects/{$project->id}/flag-outside-vendor")
            ->assertRedirect()
            ->assertSessionHas('success');

        $project->refresh();
        $this->assertTrue($project->needs_outside_vendor);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $procurement->id, 'type' => 'vendor_service.needed', 'related_id' => $project->id,
        ]);

        // Sekarang Procurement melihatnya dan bisa mengisi deal vendor.
        $this->actingAs($procurement)->get('/procurement/project-procurements')
            ->assertInertia(fn ($page) => $page->has('projects', 1)->where('projects.0.id', $project->id));

        $vendor = Vendor::create(['name' => 'Vendor Jasa', 'provides_technical' => true]);
        $this->actingAs($procurement)->put("/procurement/project-procurements/{$project->id}/vendor-service", [
            'vendor_id' => $vendor->id, 'total_fee' => 5000000, 'terms' => 'pay_at_end',
            'bank_name' => 'BCA', 'account_number' => '123', 'account_holder' => 'PT Vendor',
        ])->assertRedirect();

        // Timnya yang sudah ditugaskan sebelumnya tidak ikut hilang.
        $this->assertCount(1, $project->fresh()->technicians);

        // SOW sekarang bisa dibuat.
        $this->assertTrue($operational->can('viewSow', $project->fresh()));
        $this->assertTrue($operational->can('manageSow', $project->fresh()));
    }

    public function test_can_flag_even_when_internal_team_already_assigned_or_bast_submitted(): void
    {
        $project = $this->serviceOnlyInternalProject();
        $operational = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $technician = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $this->actingAs($operational)->put("/operational/projects/{$project->id}/technicians", [
            'technician_ids' => [$technician->id], 'leader_id' => $technician->id,
        ]);
        $project->bastRecords()->create([
            'status' => 'submitted', 'submitted_by' => $technician->id, 'submitted_at' => now(),
        ]);

        $this->actingAs($operational)->post("/operational/projects/{$project->id}/flag-outside-vendor")
            ->assertSessionHas('success');

        $this->assertTrue($project->fresh()->needs_outside_vendor);
    }

    public function test_cannot_flag_twice_or_once_a_vendor_is_already_assigned(): void
    {
        $project = $this->serviceOnlyInternalProject();
        $operational = User::factory()->create(['role' => 'operational', 'is_active' => true]);

        $this->actingAs($operational)->post("/operational/projects/{$project->id}/flag-outside-vendor")->assertSessionHas('success');
        // Sudah ditandai sebelumnya — policy langsung menolak sebelum sampai ke Action.
        $this->actingAs($operational)->post("/operational/projects/{$project->id}/flag-outside-vendor")
            ->assertForbidden();

        $vendor = Vendor::create(['name' => 'Vendor Jasa', 'provides_technical' => true]);
        $project->update(['needs_outside_vendor' => false, 'vendor_id' => $vendor->id]);
        // Sudah punya vendor terpasang — policy juga menolak.
        $this->actingAs($operational)->post("/operational/projects/{$project->id}/flag-outside-vendor")
            ->assertForbidden();
    }

    public function test_only_operational_can_flag(): void
    {
        $project = $this->serviceOnlyInternalProject();

        foreach (['sales', 'procurement', 'management', 'finance'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]))
                ->post("/operational/projects/{$project->id}/flag-outside-vendor")
                ->assertForbidden();
        }

        $this->assertFalse($project->fresh()->needs_outside_vendor);
    }
}
