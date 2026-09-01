<?php

namespace Tests\Feature\Sales;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_sales_area(): void
    {
        $this->get('/sales/contacts')->assertRedirectToRoute('login');
    }

    public function test_non_sales_role_cannot_access_sales_area(): void
    {
        $user = User::factory()->create(['role' => 'procurement']);

        $this->actingAs($user)->get('/sales/contacts')->assertForbidden();
        $this->actingAs($user)->get('/sales/leads')->assertForbidden();
    }

    public function test_inactive_sales_user_cannot_pass_policy(): void
    {
        $user = User::factory()->create(['role' => 'sales', 'is_active' => false]);

        $this->actingAs($user)->get('/sales/contacts')->assertForbidden();
    }
}
