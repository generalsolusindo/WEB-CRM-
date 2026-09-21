<?php

namespace Tests\Feature\Technician;

use App\Models\Attachment;
use App\Models\Bast;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TechnicianTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_member_technician_is_denied(): void
    {
        [$project] = $this->inProgressProject();
        $task = $project->tasks()->first();
        $outsider = User::factory()->create(['role' => 'technician', 'is_active' => true]);

        $this->actingAs($outsider)->get("/technician/tasks/{$task->id}")->assertForbidden();
        $this->actingAs($outsider)->post("/technician/tasks/{$task->id}/status", ['status' => 'done'])->assertForbidden();
    }

    public function test_member_updates_status_but_not_title(): void
    {
        [$project, , $member] = $this->inProgressProject();
        $this->checkIn($project, $member);
        $task = $project->tasks()->first();
        $originalTitle = $task->title;

        $this->actingAs($member)->post("/technician/tasks/{$task->id}/status", [
            'status' => 'in_progress',
            'title' => 'DIUBAH TECHNICIAN',
        ])->assertSessionHas('success');

        $task->refresh();
        $this->assertSame('in_progress', $task->status);
        $this->assertSame($originalTitle, $task->title);
    }

    public function test_status_only_editable_while_project_in_progress(): void
    {
        [$project, , $member] = $this->inProgressProject();
        $project->update(['status' => 'ready']);
        $task = $project->tasks()->first();

        $this->actingAs($member)->post("/technician/tasks/{$task->id}/status", ['status' => 'done'])
            ->assertForbidden();
    }

    public function test_before_after_photo_is_stored(): void
    {
        Storage::fake('local');
        [$project, , $member] = $this->inProgressProject();
        $this->checkIn($project, $member);
        $task = $project->tasks()->first();

        $this->actingAs($member)->post("/technician/tasks/{$task->id}/photos", [
            'category' => 'task_before',
            'photos' => [UploadedFile::fake()->image('before.jpg')],
        ])->assertSessionHas('success');

        $attachment = \App\Models\Attachment::where('category', 'task_before')->firstOrFail();
        $this->assertSame(ProjectTask::class, $attachment->attachable_type);
        Storage::disk('local')->assertExists($attachment->file_path);
    }

    public function test_photo_caption_is_optional_and_saved(): void
    {
        Storage::fake('local');
        [$project, , $member] = $this->inProgressProject();
        $this->checkIn($project, $member);
        $task = $project->tasks()->first();

        $this->actingAs($member)->post("/technician/tasks/{$task->id}/photos", [
            'category' => 'task_before',
            'caption' => '  Ruang server lt. 2  ',
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertSessionHas('success');
        $this->actingAs($member)->post("/technician/tasks/{$task->id}/photos", [
            'category' => 'task_after',
            'photos' => [UploadedFile::fake()->image('b.jpg')],
        ])->assertSessionHas('success');

        $this->assertSame('Ruang server lt. 2', Attachment::where('category', 'task_before')->value('caption'));
        $this->assertNull(Attachment::where('category', 'task_after')->value('caption'));
    }

    public function test_task_cannot_be_done_without_before_and_after_photos(): void
    {
        Storage::fake('local');
        [$project, , $member] = $this->inProgressProject();
        $this->checkIn($project, $member);
        $task = $project->tasks()->first();

        $this->actingAs($member)->post("/technician/tasks/{$task->id}/status", ['status' => 'done'])->assertSessionHas('error');
        $this->assertNotSame('done', $task->fresh()->status);

        $this->actingAs($member)->post("/technician/tasks/{$task->id}/photos", ['category' => 'task_before', 'photos' => [UploadedFile::fake()->image('a.jpg')]]);
        $this->actingAs($member)->post("/technician/tasks/{$task->id}/status", ['status' => 'done'])->assertSessionHas('error');

        $this->actingAs($member)->post("/technician/tasks/{$task->id}/photos", ['category' => 'task_after', 'photos' => [UploadedFile::fake()->image('b.jpg')]]);
        $this->actingAs($member)->post("/technician/tasks/{$task->id}/status", ['status' => 'done'])->assertSessionHas('success');
        $this->assertSame('done', $task->fresh()->status);
    }

    public function test_uploader_can_delete_photo_until_task_is_done(): void
    {
        Storage::fake('local');
        [$project, $leader, $member] = $this->inProgressProject();
        $this->checkIn($project, $member);
        $task = $project->tasks()->first();

        $this->actingAs($member)->post("/technician/tasks/{$task->id}/photos", ['category' => 'task_before', 'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('a2.jpg')]]);
        $this->actingAs($member)->post("/technician/tasks/{$task->id}/photos", ['category' => 'task_after', 'photos' => [UploadedFile::fake()->image('b.jpg')]]);
        [$first, $second] = Attachment::where('category', 'task_before')->get()->all();
        $after = Attachment::where('category', 'task_after')->firstOrFail();

        // Orang lain (bukan pengunggah) tidak boleh menghapus.
        $this->checkIn($project, $leader);
        $this->actingAs($leader)->delete("/technician/tasks/{$task->id}/photos/{$first->id}")->assertForbidden();

        $this->actingAs($member)->delete("/technician/tasks/{$task->id}/photos/{$first->id}")->assertSessionHas('success');
        $this->assertModelMissing($first);
        Storage::disk('local')->assertMissing($first->file_path);

        // Setelah Selesai, foto terkunci.
        $this->actingAs($member)->post("/technician/tasks/{$task->id}/status", ['status' => 'done'])->assertSessionHas('success');
        $this->actingAs($member)->delete("/technician/tasks/{$task->id}/photos/{$second->id}")->assertForbidden();
        $this->assertModelExists($second);

        // Dikembalikan ke Dikerjakan -> boleh hapus lagi.
        $this->actingAs($member)->post("/technician/tasks/{$task->id}/status", ['status' => 'in_progress']);
        $this->actingAs($member)->delete("/technician/tasks/{$task->id}/photos/{$after->id}")->assertSessionHas('success');
    }

    public function test_photo_of_another_task_cannot_be_deleted_through_this_task(): void
    {
        Storage::fake('local');
        [$project, , $member] = $this->inProgressProject();
        $this->checkIn($project, $member);
        $task = $project->tasks()->first();
        $other = $project->tasks()->create(['title' => 'Lain', 'status' => 'pending']);

        $this->actingAs($member)->post("/technician/tasks/{$task->id}/photos", ['category' => 'task_before', 'photos' => [UploadedFile::fake()->image('a.jpg')]]);
        $photo = Attachment::firstOrFail();

        $this->actingAs($member)->delete("/technician/tasks/{$other->id}/photos/{$photo->id}")->assertForbidden();
        $this->assertModelExists($photo);
    }

    public function test_only_leader_can_submit_bast_and_all_tasks_must_be_done(): void
    {
        Storage::fake('local');
        [$project, $leader, $member] = $this->inProgressProject();

        // member biasa tidak boleh
        $this->actingAs($member)->get("/technician/projects/{$project->id}/bast/create")->assertForbidden();

        // leader, tapi task belum done
        $this->actingAs($leader)->post("/technician/projects/{$project->id}/bast", [
            'documents' => [UploadedFile::fake()->create('bast.pdf', 30, 'application/pdf')],
        ])->assertSessionHasErrors('bast');

        // semua task done
        $project->tasks()->update(['status' => 'done']);
        $this->actingAs($leader)->post("/technician/projects/{$project->id}/bast", [
            'notes' => 'Pekerjaan selesai.',
            'documents' => [UploadedFile::fake()->create('bast.pdf', 30, 'application/pdf')],
        ])->assertRedirect('/technician/tasks');

        $bast = Bast::firstOrFail();
        $this->assertSame('submitted', $bast->status);
        $this->assertSame($leader->id, $bast->submitted_by);
        $this->assertSame('verification', $project->fresh()->status);
    }

    public function test_technician_cannot_verify_or_complete_project(): void
    {
        [$project, $leader] = $this->inProgressProject();
        $project->update(['status' => 'verification']);
        $bast = Bast::create(['project_id' => $project->id, 'status' => 'submitted', 'submitted_at' => now()]);

        $this->actingAs($leader)->put("/operational/projects/{$project->id}/bast/{$bast->id}", ['decision' => 'approve'])
            ->assertForbidden();
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
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload("mixed"));
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

        $project->actualProcurements()->update([
            'cost_price' => 1000, 'is_paid' => true, 'status' => 'received', 'received_at' => now(),
        ]);

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

    private function checkIn(Project $project, User $technician): void
    {
        Storage::fake('local');
        $this->actingAs($technician)->post("/technician/projects/{$project->id}/checkin", [
            'photo' => UploadedFile::fake()->image('selfie.jpg'),
        ]);
    }
}
