<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TechnicianAccountTest extends TestCase
{
    use RefreshDatabase;

    private function procurement(): User
    {
        return User::factory()->create(['role' => 'procurement', 'is_active' => true]);
    }

    public function test_procurement_creates_internal_technician_account(): void
    {
        $this->actingAs($this->procurement())->post('/procurement/technicians', [
            'name' => 'Budi Surveyor',
            'username' => 'budisurveyor',
            'email' => 'budi@ho.test',
            'phone' => '0811',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertRedirect('/procurement/technicians');

        $user = User::where('email', 'budi@ho.test')->firstOrFail();
        $this->assertSame('technician', $user->role);
        $this->assertNull($user->vendor_id);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    public function test_procurement_creates_vendor_linked_technician(): void
    {
        $vendor = Vendor::create(['name' => 'CV Survey Kalimantan', 'provides_survey' => true]);

        $this->actingAs($this->procurement())->post('/procurement/technicians', [
            'name' => 'Andi Vendor',
            'username' => 'andivendor',
            'email' => 'andi@vendor.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'vendor_id' => $vendor->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'andi@vendor.test',
            'role' => 'technician',
            'vendor_id' => $vendor->id,
        ]);
        $this->assertSame(1, $vendor->technicians()->count());
    }

    public function test_password_optional_on_update_and_role_locked(): void
    {
        $tech = User::factory()->create(['role' => 'technician', 'name' => 'Lama']);
        $original = $tech->password;

        $this->actingAs($this->procurement())->put("/procurement/technicians/{$tech->id}", [
            'name' => 'Baru',
            'username' => $tech->username,
            'email' => $tech->email,
            'is_active' => false,
        ])->assertRedirect('/procurement/technicians');

        $tech->refresh();
        $this->assertSame('Baru', $tech->name);
        $this->assertSame('technician', $tech->role);
        $this->assertFalse($tech->is_active);
        $this->assertSame($original, $tech->password);
    }

    public function test_cannot_edit_non_technician_user(): void
    {
        $finance = User::factory()->create(['role' => 'finance']);

        $this->actingAs($this->procurement())->get("/procurement/technicians/{$finance->id}/edit")
            ->assertNotFound();
    }

    public function test_duplicate_email_rejected(): void
    {
        User::factory()->create(['email' => 'dup@test.test']);

        $this->actingAs($this->procurement())->post('/procurement/technicians', [
            'name' => 'X',
            'username' => 'xduptech',
            'email' => 'dup@test.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('email');
    }

    public function test_non_procurement_cannot_manage_technician_accounts(): void
    {
        foreach (['sales', 'operational', 'technician', 'finance'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get('/procurement/technicians')->assertForbidden();
            $this->actingAs($user)->post('/procurement/technicians', [
                'name' => 'X', 'email' => "x-{$role}@t.test",
                'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
            ])->assertForbidden();
        }
    }

    public function test_procurement_can_upload_ktp_and_nik_when_creating_technician_account(): void
    {
        Storage::fake('local');

        $this->actingAs($this->procurement())->post('/procurement/technicians', [
            'name' => 'Budi Surveyor',
            'username' => 'budiktp',
            'email' => 'budi-ktp@ho.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'nik' => '3201234567890003',
            'ktp_document' => UploadedFile::fake()->image('ktp.jpg'),
        ])->assertRedirect();

        $user = User::where('email', 'budi-ktp@ho.test')->firstOrFail();
        $this->assertSame('3201234567890003', $user->nik);
        $this->assertNotNull($user->ktpDocument());
        $this->assertDatabaseHas('attachments', [
            'attachable_type' => User::class,
            'attachable_id' => $user->id,
            'category' => 'ktp_document',
        ]);
    }

    public function test_procurement_can_replace_ktp_document_when_updating_technician_account(): void
    {
        Storage::fake('local');
        $tech = User::factory()->create(['role' => 'technician']);

        $this->actingAs($this->procurement())->put("/procurement/technicians/{$tech->id}", [
            'name' => $tech->name,
            'username' => $tech->username,
            'email' => $tech->email,
            'nik' => '3201234567890004',
            'ktp_document' => UploadedFile::fake()->image('ktp-updated.jpg'),
        ])->assertRedirect();

        $tech->refresh();
        $this->assertSame('3201234567890004', $tech->nik);
        $this->assertNotNull($tech->ktpDocument());
    }

    public function test_vendor_survey_flags_persist(): void
    {
        $this->actingAs($this->procurement())->post('/procurement/vendors', [
            'name' => 'PT Jangkauan Luas',
            'city' => 'Balikpapan',
            'coverage_area' => 'Kalimantan Timur',
            'provides_survey' => true,
            'provides_technical' => false,
        ])->assertRedirect();

        $vendor = Vendor::where('name', 'PT Jangkauan Luas')->firstOrFail();
        $this->assertSame('Balikpapan', $vendor->city);
        $this->assertTrue($vendor->provides_survey);
        $this->assertFalse($vendor->provides_technical);
    }
}
