<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_can_create_vendor_pic_account(): void
    {
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $vendor = Vendor::create(['name' => 'Vendor A', 'provides_technical' => true]);

        $this->actingAs($procurement)->post('/procurement/vendor-accounts', [
            'name' => 'PIC Vendor A',
            'email' => 'pic-a@vendor.test',
            'phone' => '08123456789',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'vendor_id' => $vendor->id,
        ])->assertRedirect();

        $account = User::where('email', 'pic-a@vendor.test')->firstOrFail();
        $this->assertSame('vendor', $account->role);
        $this->assertSame($vendor->id, $account->vendor_id);
    }

    public function test_a_vendor_can_only_have_one_pic_account(): void
    {
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $vendor = Vendor::create(['name' => 'Vendor A', 'provides_technical' => true]);
        User::factory()->create(['role' => 'vendor', 'vendor_id' => $vendor->id]);

        $this->actingAs($procurement)->post('/procurement/vendor-accounts', [
            'name' => 'PIC Kedua',
            'email' => 'pic-kedua@vendor.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'vendor_id' => $vendor->id,
        ])->assertSessionHasErrors('vendor_id');

        $this->assertDatabaseMissing('users', ['email' => 'pic-kedua@vendor.test']);
    }

    public function test_non_procurement_cannot_manage_vendor_accounts(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $vendor = Vendor::create(['name' => 'Vendor A', 'provides_technical' => true]);

        $this->actingAs($sales)->get('/procurement/vendor-accounts')->assertForbidden();
        $this->actingAs($sales)->post('/procurement/vendor-accounts', [
            'name' => 'X', 'email' => 'x@vendor.test', 'password' => 'password123',
            'password_confirmation' => 'password123', 'vendor_id' => $vendor->id,
        ])->assertForbidden();
    }
}
