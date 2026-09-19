<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            'username' => 'picvendora',
            'email' => 'pic-a@vendor.test',
            'phone' => '08123456789',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'vendor_id' => $vendor->id,
        ])->assertRedirect();

        $account = User::where('email', 'pic-a@vendor.test')->firstOrFail();
        $this->assertSame('vendor', $account->role);
        $this->assertSame($vendor->id, $account->vendor_id);
        $this->assertSame('picvendora', $account->username);
    }

    public function test_a_vendor_can_only_have_one_pic_account(): void
    {
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $vendor = Vendor::create(['name' => 'Vendor A', 'provides_technical' => true]);
        User::factory()->create(['role' => 'vendor', 'vendor_id' => $vendor->id]);

        $this->actingAs($procurement)->post('/procurement/vendor-accounts', [
            'name' => 'PIC Kedua',
            'username' => 'pickedua',
            'email' => 'pic-kedua@vendor.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'vendor_id' => $vendor->id,
        ])->assertSessionHasErrors('vendor_id');

        $this->assertDatabaseMissing('users', ['email' => 'pic-kedua@vendor.test']);
    }

    public function test_vendor_pic_can_keep_vendor_role_and_still_qualify_for_technician_and_surveyor_work(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor Multi Func', 'provides_technical' => true, 'provides_survey' => true]);
        $pic = User::factory()->create([
            'role' => 'vendor',
            'vendor_id' => $vendor->id,
            'can_technician' => true,
            'can_surveyor' => true,
            'is_active' => true,
        ]);

        $this->assertSame('vendor', $pic->role);
        $this->assertTrue($pic->canWorkAsTechnician());
        $this->assertTrue($pic->canWorkAsSurveyor());
        $this->assertTrue(User::query()
            ->where(fn ($q) => $q->where('role', 'technician')->orWhere('can_technician', true))
            ->where('id', $pic->id)
            ->exists());
        $this->assertTrue(User::query()
            ->where(fn ($q) => $q->where('role', 'technician')->orWhere('can_surveyor', true))
            ->where('id', $pic->id)
            ->exists());
    }

    public function test_procurement_can_upload_ktp_and_nik_when_creating_vendor_account(): void
    {
        Storage::fake('local');
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $vendor = Vendor::create(['name' => 'Vendor A', 'provides_technical' => true]);

        $this->actingAs($procurement)->post('/procurement/vendor-accounts', [
            'name' => 'PIC Vendor A',
            'username' => 'picvendorktp',
            'email' => 'pic-a@vendor.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'vendor_id' => $vendor->id,
            'nik' => '3201234567890001',
            'ktp_document' => UploadedFile::fake()->image('ktp.jpg'),
        ])->assertRedirect();

        $account = User::where('email', 'pic-a@vendor.test')->firstOrFail();
        $this->assertSame('3201234567890001', $account->nik);
        $this->assertNotNull($account->ktpDocument());
        $this->assertDatabaseHas('attachments', [
            'attachable_type' => User::class,
            'attachable_id' => $account->id,
            'category' => 'ktp_document',
        ]);
    }

    public function test_procurement_can_replace_ktp_document_when_updating_vendor_account(): void
    {
        Storage::fake('local');
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $vendor = Vendor::create(['name' => 'Vendor A', 'provides_technical' => true]);
        $account = User::factory()->create(['role' => 'vendor', 'vendor_id' => $vendor->id]);

        $this->actingAs($procurement)->put("/procurement/vendor-accounts/{$account->id}", [
            'name' => $account->name,
            'username' => $account->username,
            'email' => $account->email,
            'vendor_id' => $vendor->id,
            'nik' => '3201234567890002',
            'ktp_document' => UploadedFile::fake()->image('ktp-updated.jpg'),
        ])->assertRedirect();

        $account->refresh();
        $this->assertSame('3201234567890002', $account->nik);
        $this->assertNotNull($account->ktpDocument());
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
