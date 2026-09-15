<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_username_and_password(): void
    {
        $user = User::factory()->create([
            'username' => 'hana',
            'password' => Hash::make('password123'),
            'role' => 'sales',
        ]);

        $response = $this->post('/login', [
            'username' => 'hana',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_username_is_lowercased_before_matching(): void
    {
        $user = User::factory()->create([
            'username' => 'hana',
            'password' => Hash::make('password123'),
        ]);

        $this->post('/login', [
            'username' => 'HANA',
            'password' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'username' => 'hana',
            'password' => Hash::make('password123'),
        ]);

        $this->post('/login', [
            'username' => 'hana',
            'password' => 'salah',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_inactive_account_cannot_login(): void
    {
        User::factory()->create([
            'username' => 'hana',
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);

        $this->post('/login', [
            'username' => 'hana',
            'password' => 'password123',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_email_field_is_no_longer_accepted_for_login(): void
    {
        User::factory()->create([
            'username' => 'hana',
            'email' => 'hana@gscrm.test',
            'password' => Hash::make('password123'),
        ]);

        $this->post('/login', [
            'email' => 'hana@gscrm.test',
            'password' => 'password123',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }
}
