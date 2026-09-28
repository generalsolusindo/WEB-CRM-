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

class SurveyResultDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_surveyor_uploads_image_and_pdf_result_documents_after_checking_in(): void
    {
        Storage::fake('local');
        [$survey, $surveyor] = $this->briefedSurvey(checkIn: true);

        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/result-documents", [
            'file' => UploadedFile::fake()->image('denah.jpg'),
        ])->assertRedirect()->assertSessionHas('success');
        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/result-documents", [
            'file' => UploadedFile::fake()->create('hasil.pdf', 200, 'application/pdf'),
        ])->assertRedirect();

        $documents = Attachment::where('category', 'survey_result_document')->get();
        $this->assertCount(2, $documents);
        foreach ($documents as $document) {
            $this->assertSame(Survey::class, $document->attachable_type);
            $this->assertSame($survey->id, $document->attachable_id);
            $this->assertSame($surveyor->id, $document->uploaded_by);
            Storage::disk('local')->assertExists($document->file_path);
        }

        $this->actingAs($surveyor)->get("/technician/surveys/{$survey->id}")
            ->assertInertia(fn ($page) => $page
                ->where('canManageResultDocuments', true)
                ->has('resultDocuments', 2));
    }

    public function test_operational_only_views_the_documents_and_cannot_upload_or_delete(): void
    {
        Storage::fake('local');
        [$survey, $surveyor] = $this->briefedSurvey(checkIn: true);
        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/result-documents", [
            'file' => UploadedFile::fake()->image('denah.jpg'),
        ]);
        $document = Attachment::where('category', 'survey_result_document')->firstOrFail();
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);

        $this->actingAs($ops)->get("/operational/surveys/{$survey->id}")
            ->assertInertia(fn ($page) => $page->has('resultDocuments', 1)->has('checkIns', 1));

        $this->actingAs($ops)->post("/operational/surveys/{$survey->id}/result-documents", [
            'file' => UploadedFile::fake()->image('x.jpg'),
        ])->assertNotFound();
        $this->actingAs($ops)->post("/technician/surveys/{$survey->id}/result-documents", [
            'file' => UploadedFile::fake()->image('x.jpg'),
        ])->assertForbidden();
        $this->actingAs($ops)->delete("/technician/surveys/{$survey->id}/result-documents/{$document->id}")->assertForbidden();

        $this->assertSame(1, Attachment::where('category', 'survey_result_document')->count());
    }

    public function test_only_assigned_surveyor_who_checked_in_during_a_running_survey_can_upload(): void
    {
        Storage::fake('local');
        [$survey, $surveyor] = $this->briefedSurvey(checkIn: false);
        $file = fn () => ['file' => UploadedFile::fake()->image('x.jpg')];

        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/result-documents", $file())->assertForbidden();

        $stranger = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $this->actingAs($stranger)->post("/technician/surveys/{$survey->id}/result-documents", $file())->assertForbidden();

        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/checkin", ['photo' => UploadedFile::fake()->image('selfie.jpg')]);
        $survey->update(['status' => 'report_review']);
        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/result-documents", $file())->assertForbidden();

        $this->assertSame(0, Attachment::where('category', 'survey_result_document')->count());
    }

    public function test_only_images_and_pdf_up_to_5mb_are_accepted(): void
    {
        Storage::fake('local');
        [$survey, $surveyor] = $this->briefedSurvey(checkIn: true);

        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/result-documents", [
            'file' => UploadedFile::fake()->create('hasil.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertSessionHasErrors('file');
        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/result-documents", [
            'file' => UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf'),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, Attachment::where('category', 'survey_result_document')->count());
    }

    public function test_surveyor_deletes_a_result_document_and_its_file_but_not_other_attachments(): void
    {
        Storage::fake('local');
        [$survey, $surveyor] = $this->briefedSurvey(checkIn: true);
        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/result-documents", ['file' => UploadedFile::fake()->image('a.jpg')]);
        $document = Attachment::where('category', 'survey_result_document')->firstOrFail();
        $selfie = Attachment::where('category', 'checkin_selfie')->firstOrFail();

        $this->actingAs($surveyor)->delete("/technician/surveys/{$survey->id}/result-documents/{$selfie->id}")->assertNotFound();
        $this->assertDatabaseHas('attachments', ['id' => $selfie->id]);

        $this->actingAs($surveyor)->delete("/technician/surveys/{$survey->id}/result-documents/{$document->id}")->assertRedirect();
        $this->assertDatabaseMissing('attachments', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($document->file_path);
    }

    /** @return array{Survey, User} [survey, surveyor] */
    private function briefedSurvey(bool $checkIn): array
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $operational = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $surveyor = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified']);
        $survey = $lead->surveys()->create([
            'requested_by' => $sales->id, 'site_address' => 'Jl. Z', 'site_region' => 'Solo',
            'delivery_mode' => 'internal', 'billable' => false, 'cost' => 0, 'status' => 'awaiting_briefing',
        ]);

        $this->actingAs($operational)->post("/operational/surveys/{$survey->id}/brief", [
            'briefing' => 'Cek lokasi', 'surveyor_ids' => [$surveyor->id], 'leader_id' => $surveyor->id,
        ]);

        if ($checkIn) {
            $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/checkin", [
                'photo' => UploadedFile::fake()->image('selfie.jpg'),
            ]);
        }

        return [$survey->fresh(), $surveyor];
    }
}
