<?php

namespace Tests\Feature\Management;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagerAccountTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => 'management', 'is_active' => true]);
    }

    public function test_manager_creates_a_project_manager_account(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager)->post('/management/project-managers', [
            'name' => 'Budi PM',
            'email' => 'budi.pm@gscrm.test',
            'phone' => '0812-0000-0001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => true,
        ])->assertRedirect('/management/project-managers');

        $pm = User::where('email', 'budi.pm@gscrm.test')->firstOrFail();
        $this->assertSame('project_manager', $pm->role);
        $this->assertTrue($pm->is_active);
    }

    public function test_manager_updates_a_project_manager_account(): void
    {
        $manager = $this->manager();
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true, 'name' => 'Lama']);

        $this->actingAs($manager)->put("/management/project-managers/{$pm->id}", [
            'name' => 'Baru',
            'email' => $pm->email,
            'is_active' => false,
        ])->assertRedirect('/management/project-managers');

        $pm->refresh();
        $this->assertSame('Baru', $pm->name);
        $this->assertFalse($pm->is_active);
    }

    public function test_project_manager_cannot_manage_project_manager_accounts(): void
    {
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);

        $this->actingAs($pm)->get('/management/project-managers')->assertForbidden();
        $this->actingAs($pm)->post('/management/project-managers', [
            'name' => 'X', 'email' => 'x@gscrm.test', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertForbidden();
    }

    public function test_other_roles_cannot_access_project_manager_accounts(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);

        $this->actingAs($sales)->get('/management/project-managers')->assertForbidden();
        $this->actingAs($procurement)->get('/management/project-managers')->assertForbidden();
    }

    public function test_inactive_manager_cannot_manage_project_managers(): void
    {
        $manager = User::factory()->create(['role' => 'management', 'is_active' => false]);

        $this->actingAs($manager)->get('/management/project-managers')->assertForbidden();
    }
}
