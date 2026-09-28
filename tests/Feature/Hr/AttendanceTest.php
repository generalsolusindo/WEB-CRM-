<?php

namespace Tests\Feature\Hr;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Survey;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsProcurementProject;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use BuildsProcurementProject;
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->hr = User::factory()->create(['role' => 'hr', 'is_active' => true]);
    }

    public function test_hr_sees_project_and_survey_attendance_with_photos_and_duration(): void
    {
        $vendor = Vendor::create(['name' => 'PT Vendor Uji']);
        $technician = User::factory()->create(['role' => 'technician', 'name' => 'Budi Teknisi', 'vendor_id' => $vendor->id]);
        $project = $this->materialProject();
        $survey = $this->survey('Solo');

        $this->attend($project, $technician, '2026-09-20 08:00:00', '2026-09-20 16:30:00');
        $this->attend($survey, $technician, '2026-09-21 09:00:00', null);

        $rows = collect($this->actingAs($this->hr)->get('/hr/attendance')
            ->assertOk()
            ->viewData('page')['props']['attendance']['data']);

        $this->assertCount(2, $rows);

        $projectRow = $rows->firstWhere('type', 'project');
        $this->assertSame('Budi Teknisi', $projectRow['technician']);
        $this->assertSame('PT Vendor Uji', $projectRow['vendor']);
        $this->assertSame('PRJ-'.str_pad((string) $project->id, 6, '0', STR_PAD_LEFT), $projectRow['job_code']);
        $this->assertSame(510, $projectRow['duration_minutes']);
        $this->assertNotNull($projectRow['check_in_url']);
        $this->assertNotNull($projectRow['check_out_url']);

        $surveyRow = $rows->firstWhere('type', 'survey');
        $this->assertSame($survey->code, $surveyRow['job_code']);
        $this->assertNotNull($surveyRow['check_in_url']);
        $this->assertNull($surveyRow['check_out_url']);
        $this->assertNull($surveyRow['duration_minutes']);
    }

    public function test_filters_by_type_technician_and_date(): void
    {
        $budi = User::factory()->create(['role' => 'technician', 'name' => 'Budi']);
        $sari = User::factory()->create(['role' => 'technician', 'name' => 'Sari']);
        $project = $this->materialProject();
        $survey = $this->survey('Solo');

        $this->attend($project, $budi, '2026-09-10 08:00:00', '2026-09-10 12:00:00');
        $this->attend($survey, $sari, '2026-09-20 08:00:00', '2026-09-20 12:00:00');

        $count = fn (string $query) => count($this->actingAs($this->hr)->get("/hr/attendance{$query}")
            ->assertOk()->viewData('page')['props']['attendance']['data']);

        $this->assertSame(2, $count(''));
        $this->assertSame(1, $count('?type=survey'));
        $this->assertSame(1, $count('?type=project'));
        $this->assertSame(1, $count("?technician={$budi->id}"));
        $this->assertSame(1, $count('?from=2026-09-15&to=2026-09-30'));
        $this->assertSame(0, $count("?type=survey&technician={$budi->id}"));
        // urutan tanggal terbalik tidak boleh error
        $this->assertSame(1, $count('?from=2026-09-30&to=2026-09-15'));
    }

    public function test_checkout_is_paired_only_with_the_same_person_and_job(): void
    {
        $budi = User::factory()->create(['role' => 'technician', 'name' => 'Budi']);
        $sari = User::factory()->create(['role' => 'technician', 'name' => 'Sari']);
        $survey = $this->survey('Solo');

        $this->attend($survey, $budi, '2026-09-20 08:00:00', null);
        $this->attend($survey, $sari, '2026-09-20 08:30:00', '2026-09-20 11:30:00');

        $rows = collect($this->actingAs($this->hr)->get('/hr/attendance')->viewData('page')['props']['attendance']['data']);

        $this->assertNull($rows->firstWhere('technician', 'Budi')['check_out_url']);
        $this->assertSame(180, $rows->firstWhere('technician', 'Sari')['duration_minutes']);
    }

    public function test_only_hr_can_open_the_page(): void
    {
        foreach (['sales', 'finance', 'technician', 'management', 'operational', 'project_manager', 'procurement'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]))
                ->get('/hr/attendance')
                ->assertForbidden();
        }
    }

    private function survey(string $region): Survey
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'company_name' => 'PT Cust', 'created_by' => $sales->id]);
        $lead = Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified']);

        return $lead->surveys()->create([
            'requested_by' => $sales->id, 'site_address' => 'a', 'site_region' => $region,
            'delivery_mode' => 'internal', 'billable' => false, 'cost' => 0, 'status' => 'in_progress',
        ]);
    }

    private function attend(Model $job, User $technician, string $in, ?string $out): void
    {
        foreach (['checkin_selfie' => $in, 'checkout_selfie' => $out] as $category => $at) {
            if ($at === null) {
                continue;
            }

            $attachment = $job->attachments()->create([
                'category' => $category,
                'file_path' => UploadedFile::fake()->image("{$category}.jpg")->store('attendance'),
                'uploaded_by' => $technician->id,
            ]);
            $attachment->forceFill(['created_at' => $at])->save();
        }
    }
}
