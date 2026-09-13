<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdministratorSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_uploads_and_replaces_signature(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'administrator', 'is_active' => true]);

        $this->actingAs($admin)->get('/admin/signature')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('signatureUrl', null));

        $this->actingAs($admin)->post('/admin/signature', [
            'signature' => UploadedFile::fake()->image('sig1.png'),
        ])->assertRedirect();

        $admin->refresh();
        $this->assertNotNull($admin->signature_path);
        $firstPath = $admin->signature_path;

        $this->actingAs($admin)->post('/admin/signature', [
            'signature' => UploadedFile::fake()->image('sig2.png'),
        ])->assertRedirect();

        $admin->refresh();
        $this->assertNotSame($firstPath, $admin->signature_path);
        Storage::disk('local')->assertMissing($firstPath);
    }

    public function test_non_administrator_cannot_upload_signature(): void
    {
        Storage::fake('local');
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);

        $this->actingAs($ops)->get('/admin/signature')->assertForbidden();
        $this->actingAs($ops)->post('/admin/signature', [
            'signature' => UploadedFile::fake()->image('sig.png'),
        ])->assertForbidden();
    }
}
