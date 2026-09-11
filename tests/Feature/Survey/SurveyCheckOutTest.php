<?php

namespace Tests\Feature\Survey;

use App\Models\Attachment;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SurveyCheckOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_surveyor_cannot_check_out_before_checking_in(): void
    {
        Storage::fake('local');
        [$survey, $surveyor] = $this->briefedSurvey();

        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/checkout", [
            'photo' => UploadedFile::fake()->image('checkout.jpg'),
        ])->assertForbidden();
    }

    public function test_surveyor_checks_out_after_checking_in_and_selfie_is_stored(): void
    {
        Storage::fake('local');
        [$survey, $surveyor] = $this->briefedSurvey();

        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/checkin", [
            'photo' => UploadedFile::fake()->image('selfie.jpg'),
        ]);

        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/checkout", [
            'photo' => UploadedFile::fake()->image('checkout.jpg'),
        ])->assertRedirect();

        $selfie = Attachment::where('category', 'checkout_selfie')->firstOrFail();
        $this->assertSame(Survey::class, $selfie->attachable_type);
        $this->assertSame($surveyor->id, $selfie->uploaded_by);
        Storage::disk('local')->assertExists($selfie->file_path);
    }

    public function test_surveyor_cannot_check_out_twice(): void
    {
        Storage::fake('local');
        [$survey, $surveyor] = $this->briefedSurvey();

        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/checkin", [
            'photo' => UploadedFile::fake()->image('selfie.jpg'),
        ]);
        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/checkout", [
            'photo' => UploadedFile::fake()->image('checkout1.jpg'),
        ]);

        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/checkout", [
            'photo' => UploadedFile::fake()->image('checkout2.jpg'),
        ])->assertForbidden();

        $this->assertSame(1, Attachment::where('category', 'checkout_selfie')->count());
    }

    /** @return array{Survey, User} [survey, surveyor] */
    private function briefedSurvey(): array
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $operational = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $surveyor = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified']);
        $survey = $lead->surveys()->create([
            'requested_by' => $sales->id, 'site_address' => 'Jl. Z', 'site_region' => 'Solo',
            'delivery_mode' => 'internal', 'billable' => false, 'cost' => 0,
            'status' => 'awaiting_briefing',
        ]);

        $this->actingAs($operational)->post("/operational/surveys/{$survey->id}/brief", [
            'briefing' => 'Cek lokasi',
            'surveyor_ids' => [$surveyor->id],
            'leader_id' => $surveyor->id,
        ]);

        return [$survey->fresh(), $surveyor];
    }
}
