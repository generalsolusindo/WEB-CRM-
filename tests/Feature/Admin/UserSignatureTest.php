<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_lists_only_eligible_roles(): void
    {
        $admin = User::factory()->create(['role' => 'administrator', 'is_active' => true]);
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true, 'name' => 'Ops A']);
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true, 'name' => 'PM A']);
        $mgmt = User::factory()->create(['role' => 'management', 'is_active' => true, 'name' => 'Mgmt A']);
        User::factory()->create(['role' => 'sales', 'is_active' => true, 'name' => 'Sales A']);
        User::factory()->create(['role' => 'operational', 'is_active' => false, 'name' => 'Ops Nonaktif']);

        $response = $this->actingAs($admin)->get('/admin/user-signatures')->assertOk();
        $ids = collect($response->viewData('page')['props']['users'])->pluck('id')->sort()->values();

        $this->assertSame(collect([$ops->id, $pm->id, $mgmt->id])->sort()->values()->all(), $ids->all());
    }

    public function test_administrator_uploads_signature_for_operational_user(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'administrator', 'is_active' => true]);
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);

        $this->actingAs($admin)->post("/admin/user-signatures/{$ops->id}", [
            'signature' => UploadedFile::fake()->image('sig1.png'),
        ])->assertRedirect();

        $ops->refresh();
        $this->assertNotNull($ops->signature_path);
        $firstPath = $ops->signature_path;
        Storage::disk('local')->assertExists($firstPath);

        $this->actingAs($admin)->post("/admin/user-signatures/{$ops->id}", [
            'signature' => UploadedFile::fake()->image('sig2.png'),
        ])->assertRedirect();

        $ops->refresh();
        $this->assertNotSame($firstPath, $ops->signature_path);
        Storage::disk('local')->assertMissing($firstPath);
    }

    public function test_administrator_cannot_upload_signature_for_ineligible_role(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'administrator', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales', 'is_active' => true]);

        $this->actingAs($admin)->post("/admin/user-signatures/{$sales->id}", [
            'signature' => UploadedFile::fake()->image('sig.png'),
        ])->assertSessionHasErrors('signature');

        $this->assertNull($sales->fresh()->signature_path);
    }

    public function test_non_administrator_cannot_manage_user_signatures(): void
    {
        Storage::fake('local');
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);

        $this->actingAs($management)->get('/admin/user-signatures')->assertForbidden();
        $this->actingAs($management)->post("/admin/user-signatures/{$ops->id}", [
            'signature' => UploadedFile::fake()->image('sig.png'),
        ])->assertForbidden();
    }
}
