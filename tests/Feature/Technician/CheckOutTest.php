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

class CheckOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_technician_cannot_check_out_before_checking_in(): void
    {
        Storage::fake('local');
        [$project, , $member] = $this->inProgressProject();

        $this->actingAs($member)->post("/technician/projects/{$project->id}/checkout", [
            'photo' => UploadedFile::fake()->image('checkout.jpg'),
        ])->assertForbidden();
    }

    public function test_technician_checks_out_after_checking_in_and_selfie_is_stored(): void
    {
        Storage::fake('local');
        [$project, , $member] = $this->inProgressProject();

        $this->actingAs($member)->post("/technician/projects/{$project->id}/checkin", [
            'photo' => UploadedFile::fake()->image('selfie.jpg'),
        ]);

        $this->actingAs($member)->post("/technician/projects/{$project->id}/checkout", [
            'photo' => UploadedFile::fake()->image('checkout.jpg'),
        ])->assertRedirect();

        $selfie = Attachment::where('category', 'checkout_selfie')->firstOrFail();
        $this->assertSame(Project::class, $selfie->attachable_type);
        $this->assertSame($member->id, $selfie->uploaded_by);
        Storage::disk('local')->assertExists($selfie->file_path);
    }

    public function test_technician_cannot_check_out_twice(): void
    {
        Storage::fake('local');
        [$project, , $member] = $this->inProgressProject();

        $this->actingAs($member)->post("/technician/projects/{$project->id}/checkin", [
            'photo' => UploadedFile::fake()->image('selfie.jpg'),
        ]);
        $this->actingAs($member)->post("/technician/projects/{$project->id}/checkout", [
            'photo' => UploadedFile::fake()->image('checkout1.jpg'),
        ]);

        $this->actingAs($member)->post("/technician/projects/{$project->id}/checkout", [
            'photo' => UploadedFile::fake()->image('checkout2.jpg'),
        ])->assertForbidden();

        $this->assertSame(1, Attachment::where('category', 'checkout_selfie')->count());
    }

    public function test_checkout_is_per_technician_not_shared_across_team(): void
    {
        Storage::fake('local');
        [$project, $leader, $member] = $this->inProgressProject();

        $this->actingAs($leader)->post("/technician/projects/{$project->id}/checkin", [
            'photo' => UploadedFile::fake()->image('selfie.jpg'),
        ]);
        $this->actingAs($leader)->post("/technician/projects/{$project->id}/checkout", [
            'photo' => UploadedFile::fake()->image('checkout.jpg'),
        ])->assertRedirect();

        // member belum absen datang sama sekali -> checkout tetap ditolak untuknya
        $this->actingAs($member)->post("/technician/projects/{$project->id}/checkout", [
            'photo' => UploadedFile::fake()->image('checkout.jpg'),
        ])->assertForbidden();
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
}
