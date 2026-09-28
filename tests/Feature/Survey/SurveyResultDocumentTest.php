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

    public function test_operational_uploads_image_and_pdf_result_documents_and_sees_them_listed(): void
    {
        Storage::fake('local');
        [$survey, $ops] = $this->survey('report_review');

        $this->actingAs($ops)->post("/operational/surveys/{$survey->id}/result-documents", [
            'file' => UploadedFile::fake()->image('denah.jpg'),
        ])->assertRedirect()->assertSessionHas('success');
        $this->actingAs($ops)->post("/operational/surveys/{$survey->id}/result-documents", [
            'file' => UploadedFile::fake()->create('hasil.pdf', 200, 'application/pdf'),
        ])->assertRedirect();

        $documents = Attachment::where('category', 'survey_result_document')->get();
        $this->assertCount(2, $documents);
        foreach ($documents as $document) {
            $this->assertSame(Survey::class, $document->attachable_type);
            $this->assertSame($survey->id, $document->attachable_id);
            $this->assertSame($ops->id, $document->uploaded_by);
            Storage::disk('local')->assertExists($document->file_path);
        }

        $this->actingAs($ops)->get("/operational/surveys/{$survey->id}")
            ->assertInertia(fn ($page) => $page
                ->where('canManageResultDocuments', true)
                ->has('resultDocuments', 2)
                ->has('checkIns', 0));
    }

    public function test_only_images_and_pdf_up_to_5mb_are_accepted(): void
    {
        Storage::fake('local');
        [$survey, $ops] = $this->survey('report_review');

        $this->actingAs($ops)->post("/operational/surveys/{$survey->id}/result-documents", [
            'file' => UploadedFile::fake()->create('hasil.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertSessionHasErrors('file');
        $this->actingAs($ops)->post("/operational/surveys/{$survey->id}/result-documents", [
            'file' => UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf'),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, Attachment::where('category', 'survey_result_document')->count());
    }

    public function test_other_roles_and_early_or_cancelled_surveys_cannot_take_uploads(): void
    {
        Storage::fake('local');
        [$survey, $ops] = $this->survey('report_review');
        $file = fn () => ['file' => UploadedFile::fake()->image('x.jpg')];

        foreach (['sales', 'finance', 'technician', 'management'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]))
                ->post("/operational/surveys/{$survey->id}/result-documents", $file())
                ->assertForbidden();
        }

        foreach (['requested', 'awaiting_briefing', 'cancelled'] as $status) {
            $survey->update(['status' => $status]);
            $this->actingAs($ops)->post("/operational/surveys/{$survey->id}/result-documents", $file())->assertForbidden();
        }

        $this->assertSame(0, Attachment::where('category', 'survey_result_document')->count());
    }

    public function test_operational_deletes_a_result_document_and_its_file_but_not_other_attachments(): void
    {
        Storage::fake('local');
        [$survey, $ops] = $this->survey('report_review');
        $this->actingAs($ops)->post("/operational/surveys/{$survey->id}/result-documents", ['file' => UploadedFile::fake()->image('a.jpg')]);
        $document = Attachment::where('category', 'survey_result_document')->firstOrFail();

        $selfie = $survey->attachments()->create([
            'category' => 'checkin_selfie', 'file_path' => UploadedFile::fake()->image('s.jpg')->store('checkin'), 'uploaded_by' => $ops->id,
        ]);

        $this->actingAs($ops)->delete("/operational/surveys/{$survey->id}/result-documents/{$selfie->id}")->assertNotFound();
        $this->assertDatabaseHas('attachments', ['id' => $selfie->id]);

        $this->actingAs($ops)->delete("/operational/surveys/{$survey->id}/result-documents/{$document->id}")->assertRedirect();
        $this->assertDatabaseMissing('attachments', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($document->file_path);
    }

    /** @return array{Survey, User} */
    private function survey(string $status): array
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified']);
        $survey = $lead->surveys()->create([
            'requested_by' => $sales->id, 'site_address' => 'a', 'site_region' => 'x',
            'delivery_mode' => 'internal', 'billable' => true, 'cost' => 300000, 'status' => $status,
        ]);

        return [$survey, $ops];
    }
}
