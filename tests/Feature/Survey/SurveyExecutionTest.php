<?php

namespace Tests\Feature\Survey;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SurveyExecutionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Survey, User, User} [survey, surveyor, salesUser] */
    private function briefingSurvey(): array
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $surveyor = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified']);
        $survey = $lead->surveys()->create([
            'requested_by' => $sales->id, 'site_address' => 'Jl. Z', 'site_region' => 'Solo',
            'delivery_mode' => 'internal', 'billable' => false, 'cost' => 0,
            'status' => 'awaiting_briefing',
        ]);

        return [$survey, $surveyor, $sales];
    }

    private function operational(): User
    {
        return User::factory()->create(['role' => 'operational', 'is_active' => true]);
    }

    private function brief(Survey $survey, User $surveyor, User $operational, string $briefing = 'b'): \Illuminate\Testing\TestResponse
    {
        $response = $this->actingAs($operational)->post("/operational/surveys/{$survey->id}/brief", [
            'briefing' => $briefing,
            'surveyor_ids' => [$surveyor->id],
            'leader_id' => $surveyor->id,
        ]);

        $this->checkInSurveyor($survey, $surveyor);

        return $response;
    }

    private function checkInSurveyor(Survey $survey, User $surveyor): void
    {
        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/checkin", [
            'photo' => UploadedFile::fake()->image('selfie.jpg'),
        ]);
    }

    public function test_operational_briefs_and_surveyor_is_notified(): void
    {
        [$survey, $surveyor] = $this->briefingSurvey();

        $this->brief($survey, $surveyor, $this->operational(), 'Ukur ruang server lantai 3.')->assertRedirect();

        $survey->refresh();
        $this->assertSame('in_progress', $survey->status);
        $this->assertSame('Ukur ruang server lantai 3.', $survey->briefing);
        $this->assertNotNull($survey->report);
        $this->assertSame(1, Notification::where('type', 'survey.assigned')->where('user_id', $surveyor->id)->count());
    }

    public function test_non_assigned_surveyor_cannot_view_or_edit(): void
    {
        [$survey] = $this->briefingSurvey();
        $survey->update(['status' => 'in_progress']);
        $survey->report()->create(['status' => 'draft', 'revision' => 1]);
        $other = User::factory()->create(['role' => 'technician', 'is_active' => true]);

        $this->actingAs($other)->get("/technician/surveys/{$survey->id}")->assertForbidden();
        $this->actingAs($other)->put("/technician/surveys/{$survey->id}/report", ['summary' => 'x', 'items' => []])->assertForbidden();
    }

    public function test_surveyor_saves_summary_and_items_then_submits(): void
    {
        [$survey, $surveyor] = $this->briefingSurvey();
        $operational = $this->operational();
        $this->brief($survey, $surveyor, $operational);

        $this->actingAs($surveyor)->put("/technician/surveys/{$survey->id}/report", [
            'summary' => 'Ruangan siap, butuh 2 rak dan kabel.',
            'items' => [
                ['item_name' => 'Rak server 42U', 'qty' => 2, 'unit' => 'unit', 'notes' => 'depan pintu'],
                ['item_name' => 'Kabel UTP cat6', 'qty' => 100, 'unit' => 'meter', 'notes' => null],
            ],
        ])->assertRedirect();

        $report = $survey->fresh()->report;
        $this->assertSame('Ruangan siap, butuh 2 rak dan kabel.', $report->summary);
        $this->assertSame(2, $report->items()->count());

        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/report/submit")->assertRedirect();

        $survey->refresh();
        $this->assertSame('report_review', $survey->status);
        $this->assertSame('submitted', $survey->report->status);
        $this->assertSame(1, Notification::where('type', 'survey.report_review')->where('user_id', $operational->id)->count());
    }

    public function test_cannot_submit_empty_report(): void
    {
        [$survey, $surveyor] = $this->briefingSurvey();
        $this->brief($survey, $surveyor, $this->operational());

        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/report/submit")
            ->assertSessionHasErrors('summary');
        $this->assertSame('in_progress', $survey->fresh()->status);
    }

    public function test_operational_approves_report_and_sales_is_notified(): void
    {
        [$survey, $surveyor, $sales] = $this->briefingSurvey();
        $operational = $this->operational();
        $this->brief($survey, $surveyor, $operational);
        $this->actingAs($surveyor)->put("/technician/surveys/{$survey->id}/report", [
            'summary' => 'ok', 'items' => [['item_name' => 'X', 'qty' => 1, 'unit' => 'pcs', 'notes' => null]],
        ]);
        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/report/submit");

        $this->actingAs($operational)->post("/operational/surveys/{$survey->id}/verify", [
            'decision' => 'approve',
        ])->assertRedirect();

        $survey->refresh();
        $this->assertSame('verified', $survey->status);
        $this->assertSame('verified', $survey->report->status);
        $this->assertSame(1, Notification::where('type', 'survey.verified')->where('user_id', $sales->id)->count());
    }

    public function test_reject_bumps_revision_and_allows_resubmit(): void
    {
        [$survey, $surveyor] = $this->briefingSurvey();
        $operational = $this->operational();
        $this->brief($survey, $surveyor, $operational);
        $this->actingAs($surveyor)->put("/technician/surveys/{$survey->id}/report", [
            'summary' => 'kurang', 'items' => [['item_name' => 'X', 'qty' => 1, 'unit' => 'pcs', 'notes' => null]],
        ]);
        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/report/submit");

        $this->actingAs($operational)->post("/operational/surveys/{$survey->id}/verify", [
            'decision' => 'reject',
        ])->assertSessionHasErrors('notes');

        $this->actingAs($operational)->post("/operational/surveys/{$survey->id}/verify", [
            'decision' => 'reject', 'notes' => 'Foto panel kurang jelas',
        ])->assertRedirect();

        $survey->refresh();
        $this->assertSame('in_progress', $survey->status);
        $this->assertSame('rejected', $survey->report->status);
        $this->assertSame(2, $survey->report->revision);
        $this->assertSame(1, Notification::where('type', 'survey.rework')->where('user_id', $surveyor->id)->count());

        // resubmit
        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/report/submit")->assertRedirect();
        $this->assertSame('report_review', $survey->fresh()->status);
    }

    public function test_operational_cannot_verify_before_submission(): void
    {
        [$survey] = $this->briefingSurvey();
        $survey->update(['status' => 'in_progress']);

        $this->actingAs($this->operational())->post("/operational/surveys/{$survey->id}/verify", [
            'decision' => 'approve',
        ])->assertForbidden();
    }

    public function test_surveyor_uploads_and_deletes_attachment(): void
    {
        Storage::fake('local');
        [$survey, $surveyor] = $this->briefingSurvey();
        $this->brief($survey, $surveyor, $this->operational());

        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/report/attachments", [
            'file' => UploadedFile::fake()->create('foto.jpg', 100, 'image/jpeg'),
        ])->assertRedirect();

        $attachment = $survey->fresh()->report->attachments()->firstOrFail();
        $this->assertSame('survey_report', $attachment->category);

        $this->actingAs($surveyor)->delete("/technician/surveys/{$survey->id}/report/attachments/{$attachment->id}")
            ->assertRedirect();
        $this->assertSame(0, $survey->fresh()->report->attachments()->count());
    }

    public function test_operational_assigns_multi_surveyor_team_with_one_leader(): void
    {
        [$survey, $leader] = $this->briefingSurvey();
        $member = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $operational = $this->operational();

        $this->actingAs($operational)->post("/operational/surveys/{$survey->id}/brief", [
            'briefing' => 'Bagi tugas: leader ukur, member foto.',
            'surveyor_ids' => [$leader->id, $member->id],
            'leader_id' => $leader->id,
        ])->assertRedirect();

        $survey->refresh();
        $this->assertEqualsCanonicalizing([$leader->id, $member->id], $survey->surveyors()->pluck('users.id')->all());
        $this->assertTrue($survey->isLeader($leader));
        $this->assertFalse($survey->isLeader($member));
        $this->assertSame(1, Notification::where('type', 'survey.assigned')->where('user_id', $member->id)->count());
    }

    public function test_leader_must_be_a_selected_member(): void
    {
        [$survey, $leader] = $this->briefingSurvey();
        $outsider = User::factory()->create(['role' => 'technician', 'is_active' => true]);

        $this->actingAs($this->operational())->post("/operational/surveys/{$survey->id}/brief", [
            'briefing' => 'x',
            'surveyor_ids' => [$leader->id],
            'leader_id' => $outsider->id,
        ])->assertSessionHasErrors('leader_id');
    }

    public function test_only_leader_can_submit_report(): void
    {
        [$survey, $leader] = $this->briefingSurvey();
        $member = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $operational = $this->operational();
        $this->actingAs($operational)->post("/operational/surveys/{$survey->id}/brief", [
            'briefing' => 'b',
            'surveyor_ids' => [$leader->id, $member->id],
            'leader_id' => $leader->id,
        ]);
        $this->checkInSurveyor($survey, $leader);
        $this->checkInSurveyor($survey, $member);

        // member boleh mengisi draft
        $this->actingAs($member)->put("/technician/surveys/{$survey->id}/report", [
            'summary' => 'draft dari member', 'items' => [['item_name' => 'X', 'qty' => 1, 'unit' => 'pcs', 'notes' => null]],
        ])->assertRedirect();

        // tapi tidak boleh submit
        $this->actingAs($member)->post("/technician/surveys/{$survey->id}/report/submit")->assertForbidden();

        // leader boleh submit
        $this->actingAs($leader)->post("/technician/surveys/{$survey->id}/report/submit")->assertRedirect();
        $this->assertSame('report_review', $survey->fresh()->status);
    }

    public function test_operational_can_edit_team_while_in_progress(): void
    {
        [$survey, $leader] = $this->briefingSurvey();
        $member = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $replacement = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $operational = $this->operational();
        $this->actingAs($operational)->post("/operational/surveys/{$survey->id}/brief", [
            'briefing' => 'b', 'surveyor_ids' => [$leader->id, $member->id], 'leader_id' => $leader->id,
        ]);

        $this->actingAs($operational)->patch("/operational/surveys/{$survey->id}/team", [
            'surveyor_ids' => [$leader->id, $replacement->id],
            'leader_id' => $replacement->id,
        ])->assertRedirect();

        $survey->refresh();
        $this->assertEqualsCanonicalizing([$leader->id, $replacement->id], $survey->surveyors()->pluck('users.id')->all());
        $this->assertTrue($survey->isLeader($replacement));
        $this->assertSame(1, Notification::where('type', 'survey.assigned')->where('user_id', $replacement->id)->count());
    }

    public function test_surveyor_must_check_in_before_filling_or_submitting_report(): void
    {
        [$survey, $surveyor] = $this->briefingSurvey();
        $operational = $this->operational();
        $this->actingAs($operational)->post("/operational/surveys/{$survey->id}/brief", [
            'briefing' => 'b', 'surveyor_ids' => [$surveyor->id], 'leader_id' => $surveyor->id,
        ]);

        $this->actingAs($surveyor)->put("/technician/surveys/{$survey->id}/report", [
            'summary' => 'x', 'items' => [],
        ])->assertForbidden();

        $this->actingAs($surveyor)->post("/technician/surveys/{$survey->id}/checkin", [
            'photo' => UploadedFile::fake()->image('selfie.jpg'),
        ])->assertRedirect();

        $selfie = \App\Models\Attachment::where('category', 'checkin_selfie')->firstOrFail();
        $this->assertSame(Survey::class, $selfie->attachable_type);

        $this->actingAs($surveyor)->put("/technician/surveys/{$survey->id}/report", [
            'summary' => 'sudah absen', 'items' => [],
        ])->assertRedirect();
    }
}
