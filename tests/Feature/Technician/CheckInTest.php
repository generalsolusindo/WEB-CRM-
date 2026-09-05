<?php

namespace Tests\Feature\Technician;

use App\Models\Attachment;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_technician_cannot_update_task_or_upload_photo_before_checking_in(): void
    {
        Storage::fake('local');
        [$project, , $member] = $this->inProgressProject();
        $task = $project->tasks()->first();

        $this->actingAs($member)->post("/technician/tasks/{$task->id}/status", ['status' => 'in_progress'])
            ->assertForbidden();
        $this->actingAs($member)->post("/technician/tasks/{$task->id}/photos", [
            'category' => 'task_before',
            'photos' => [UploadedFile::fake()->image('before.jpg')],
        ])->assertForbidden();
    }

    public function test_technician_checks_in_then_can_work_and_selfie_is_stored(): void
    {
        Storage::fake('local');
        [$project, , $member] = $this->inProgressProject();
        $task = $project->tasks()->first();

        $this->actingAs($member)->post("/technician/projects/{$project->id}/checkin", [
            'photo' => UploadedFile::fake()->image('selfie.jpg'),
        ])->assertRedirect();

        $selfie = Attachment::where('category', 'checkin_selfie')->firstOrFail();
        $this->assertSame(Project::class, $selfie->attachable_type);
        $this->assertSame($member->id, $selfie->uploaded_by);
        Storage::disk('local')->assertExists($selfie->file_path);

        $this->actingAs($member)->post("/technician/tasks/{$task->id}/status", ['status' => 'in_progress'])
            ->assertSessionHas('success');
    }

    public function test_checkin_is_per_technician_not_shared_across_team(): void
    {
        Storage::fake('local');
        [$project, $leader, $member] = $this->inProgressProject();

        $this->actingAs($leader)->post("/technician/projects/{$project->id}/checkin", [
            'photo' => UploadedFile::fake()->image('selfie.jpg'),
        ]);

        $task = $project->tasks()->first();
        // leader sudah absen, member belum -> member masih ditolak
        $this->actingAs($member)->post("/technician/tasks/{$task->id}/status", ['status' => 'in_progress'])
            ->assertForbidden();
    }

    public function test_multiple_before_photos_can_be_uploaded_in_one_request(): void
    {
        Storage::fake('local');
        [$project, , $member] = $this->inProgressProject();
        $this->actingAs($member)->post("/technician/projects/{$project->id}/checkin", [
            'photo' => UploadedFile::fake()->image('selfie.jpg'),
        ]);
        $task = $project->tasks()->first();

        $this->actingAs($member)->post("/technician/tasks/{$task->id}/photos", [
            'category' => 'task_before',
            'photos' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
                UploadedFile::fake()->image('c.jpg'),
            ],
        ])->assertSessionHas('success');

        $this->assertSame(3, Attachment::where('category', 'task_before')->count());
    }

    /** @return array{Project, User, User} */
    private function inProgressProject(): array
    {
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'qty' => 2, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = $lead->procurementRequests()->with('lines')->latest('id')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines()->first()->id, 'selling_price' => 1300000]],
        ]);
        $quotation = $pr->quotations()->latest('id')->firstOrFail();
        $quotation->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload('mixed'));
        $so = $quotation->salesOrder()->firstOrFail();

        $finance = User::factory()->create(['role' => 'finance']);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'dp']);
        $invoice = $so->invoices()->latest('id')->firstOrFail();
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => (float) $invoice->amount + (float) $invoice->tax_amount,
            'paid_at' => now()->toDateTimeString(),
        ]);

        $project = Project::where('sales_order_id', $so->id)->firstOrFail();
        $project->update(['status' => 'planning']);

        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        foreach ($project->actualProcurements as $item) {
            $this->actingAs($procurement)->put("/procurement/project-procurements/{$item->id}", [
                'cost_price' => 1000, 'status' => 'received',
            ]);
        }

        $leader = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $member = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $this->actingAs($ops)->post("/operational/projects/{$project->id}/tasks", ['title' => 'Instalasi']);
        $this->actingAs($ops)->put("/operational/projects/{$project->id}/technicians", [
            'technician_ids' => [$leader->id, $member->id], 'leader_id' => $leader->id,
        ]);
        $this->actingAs($ops)->post("/operational/projects/{$project->id}/ready");
        $this->actingAs($ops)->post("/operational/projects/{$project->id}/start");

        return [$project->fresh(), $leader, $member];
    }
}
