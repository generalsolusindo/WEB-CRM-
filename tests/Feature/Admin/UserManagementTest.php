<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'administrator', 'is_active' => true]);
    }

    public function test_administrator_can_create_user(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Budi Sales Baru',
            'username' => 'budisales',
            'role' => 'sales',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/admin/users');

        $user = User::where('username', 'budisales')->firstOrFail();
        $this->assertSame('sales', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_username_is_lowercased_and_must_be_unique(): void
    {
        $admin = $this->admin();
        User::factory()->create(['username' => 'existing']);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Duplikat', 'username' => 'EXISTING', 'role' => 'sales',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('username');
    }

    public function test_vendor_role_is_not_assignable_via_generic_form(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Vendor Coba', 'username' => 'vendorcoba', 'role' => 'vendor',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['username' => 'vendorcoba']);
    }

    public function test_administrator_can_reset_password_and_deactivate_other_user(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['role' => 'sales', 'username' => 'targetuser', 'is_active' => true]);

        $this->actingAs($admin)->put("/admin/users/{$target->id}", [
            'name' => $target->name,
            'username' => 'targetuser',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'is_active' => false,
        ])->assertRedirect('/admin/users');

        $target->refresh();
        $this->assertFalse($target->is_active);
        $this->assertTrue(Hash::check('newpassword123', $target->password));
    }

    public function test_password_is_optional_on_update(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['role' => 'sales', 'username' => 'targetuser']);
        $originalPassword = $target->password;

        $this->actingAs($admin)->put("/admin/users/{$target->id}", [
            'name' => 'Nama Baru', 'username' => 'targetuser', 'is_active' => true,
        ])->assertRedirect('/admin/users');

        $target->refresh();
        $this->assertSame('Nama Baru', $target->name);
        $this->assertSame($originalPassword, $target->password);
    }

    public function test_role_cannot_be_changed_via_update(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['role' => 'sales', 'username' => 'targetuser']);

        $this->actingAs($admin)->put("/admin/users/{$target->id}", [
            'name' => $target->name, 'username' => 'targetuser', 'role' => 'finance', 'is_active' => true,
        ])->assertRedirect('/admin/users');

        $this->assertSame('sales', $target->fresh()->role);
    }

    public function test_administrator_cannot_deactivate_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put("/admin/users/{$admin->id}", [
            'name' => $admin->name, 'username' => $admin->username, 'is_active' => false,
        ])->assertRedirect();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_an_administrator_can_deactivate_another_administrator(): void
    {
        // Boleh, karena si pelaku sendiri tetap aktif setelahnya (tidak pernah nol admin aktif).
        $admin = $this->admin();
        $otherAdmin = User::factory()->create(['role' => 'administrator', 'is_active' => true]);

        $this->actingAs($admin)->put("/admin/users/{$otherAdmin->id}", [
            'name' => $otherAdmin->name, 'username' => $otherAdmin->username, 'is_active' => false,
        ])->assertRedirect('/admin/users');

        $this->assertFalse($otherAdmin->fresh()->is_active);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_search_and_role_filter(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Farah Finance', 'username' => 'farahf', 'role' => 'finance']);
        User::factory()->create(['name' => 'Hana Sales', 'username' => 'hanas', 'role' => 'sales']);

        $this->actingAs($admin)->get('/admin/users?search=Farah')
            ->assertInertia(fn ($page) => $page->where('users.total', 1));

        $this->actingAs($admin)->get('/admin/users?role=sales')
            ->assertInertia(fn ($page) => $page->where('users.total', 1));
    }

    public function test_non_administrator_cannot_access_user_management(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);

        $this->actingAs($sales)->get('/admin/users')->assertForbidden();
        $this->actingAs($sales)->post('/admin/users', [
            'name' => 'X', 'username' => 'x', 'role' => 'sales',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertForbidden();
    }

    public function test_inactive_administrator_cannot_access_user_management(): void
    {
        $admin = User::factory()->create(['role' => 'administrator', 'is_active' => false]);

        $this->actingAs($admin)->get('/admin/users')->assertForbidden();
    }
}
