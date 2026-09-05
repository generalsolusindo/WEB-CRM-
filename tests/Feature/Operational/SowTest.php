<?php

namespace Tests\Feature\Operational;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Sow;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SowTest extends TestCase
{
    use RefreshDatabase;

    private function projectWithVendor(): array
    {
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'company_name' => 'PT Customer', 'address' => 'Jl. Lokasi No. 1', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
            'pic_name' => 'Avima', 'pic_phone' => '0857-0000-0000',
        ]);
        $lead->requirements()->create(['item_name' => 'ODP', 'qty' => 4, 'unit' => 'unit', 'created_by' => $sales->id]);
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
        $vendor = Vendor::create(['name' => 'Vendor A', 'provides_technical' => true, 'contact_person' => 'PIC Vendor A', 'phone' => '0811111111']);
        $project->update(['vendor_id' => $vendor->id]);
        $technician = User::factory()->create(['role' => 'technician', 'vendor_id' => $vendor->id, 'is_active' => true]);

        return [$project, $ops, $vendor, $technician];
    }

    public function test_cannot_manage_sow_before_project_marked_with_vendor(): void
    {
        [$project, $ops] = $this->projectWithVendor();
        $project->update(['vendor_id' => null]);

        $this->actingAs($ops)->get("/operational/projects/{$project->id}/sow")->assertForbidden();
        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", ['number' => 'SOW-001'])->assertForbidden();
    }

    public function test_sow_form_prefills_from_lead_and_contact(): void
    {
        [$project, $ops] = $this->projectWithVendor();

        $response = $this->actingAs($ops)->get("/operational/projects/{$project->id}/sow");
        $response->assertOk();
        $sow = $response->viewData('page')['props']['sow'];
        $this->assertSame('Jl. Lokasi No. 1', $sow['site_location']);
        $this->assertSame('PT Customer', $sow['client_name']);
        $this->assertSame('Avima', $sow['client_pic_name']);
        $this->assertSame('0857-0000-0000', $sow['client_pic_phone']);

        $vendor = $response->viewData('page')['props']['vendor'];
        $this->assertSame('PIC Vendor A', $vendor['contact_person']);

        $techOptions = $response->viewData('page')['props']['technicianOptions'];
        $this->assertCount(1, $techOptions);
    }

    public function test_operational_can_save_draft_and_submit_to_hr(): void
    {
        [$project, $ops, , $technician] = $this->projectWithVendor();
        $hr = User::factory()->create(['role' => 'hr', 'is_active' => true]);

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", [
            'number' => 'SOW-001',
            'project_name' => 'Jasa Terminasi FO',
            'technician_id' => $technician->id,
        ])->assertRedirect();

        $sow = Sow::where('project_id', $project->id)->firstOrFail();
        $this->assertSame('draft', $sow->status);

        $this->actingAs($ops)->post("/operational/projects/{$project->id}/sow/submit")->assertRedirect();
        $sow->refresh();
        $this->assertSame('pending_hr_review', $sow->status);
        $this->assertNotNull($sow->submitted_at);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $hr->id,
            'type' => 'sow.pending_hr_review',
            'related_id' => $sow->id,
        ]);
    }

    public function test_submit_requires_number_project_name_and_technician(): void
    {
        [$project, $ops] = $this->projectWithVendor();

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", ['number' => 'SOW-001']);

        $this->actingAs($ops)->post("/operational/projects/{$project->id}/sow/submit")
            ->assertSessionHasErrors('sow');

        $this->assertSame('draft', Sow::where('project_id', $project->id)->firstOrFail()->status);
    }

    public function test_cannot_edit_sow_once_pending_hr_review(): void
    {
        [$project, $ops, , $technician] = $this->projectWithVendor();

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", [
            'number' => 'SOW-001', 'project_name' => 'Jasa X', 'technician_id' => $technician->id,
        ]);
        $this->actingAs($ops)->post("/operational/projects/{$project->id}/sow/submit");

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", [
            'number' => 'SOW-002', 'project_name' => 'Jasa Y', 'technician_id' => $technician->id,
        ])->assertForbidden();

        // Tapi tetap bisa dilihat (read-only).
        $this->actingAs($ops)->get("/operational/projects/{$project->id}/sow")->assertOk();
    }

    public function test_technician_options_are_scoped_to_the_projects_vendor(): void
    {
        [$project, $ops, , $technician] = $this->projectWithVendor();
        $otherVendor = Vendor::create(['name' => 'Vendor B', 'provides_technical' => true]);
        $otherTechnician = User::factory()->create(['role' => 'technician', 'vendor_id' => $otherVendor->id, 'is_active' => true]);

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", [
            'number' => 'SOW-001', 'project_name' => 'Jasa X', 'technician_id' => $otherTechnician->id,
        ])->assertSessionHasErrors('technician_id');

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", [
            'number' => 'SOW-001', 'project_name' => 'Jasa X', 'technician_id' => $technician->id,
        ])->assertSessionDoesntHaveErrors();
    }

    public function test_can_upload_and_delete_background_images(): void
    {
        Storage::fake('local');
        [$project, $ops] = $this->projectWithVendor();
        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", ['number' => 'SOW-001']);
        $sow = Sow::where('project_id', $project->id)->firstOrFail();

        $this->actingAs($ops)->post("/operational/projects/{$project->id}/sow/images", [
            'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertRedirect();

        $this->assertSame(2, $sow->attachments()->count());
        $image = $sow->attachments()->first();

        $this->actingAs($ops)->delete("/operational/projects/{$project->id}/sow/images/{$image->id}")->assertRedirect();
        $this->assertSame(1, $sow->attachments()->count());
    }

    public function test_non_operational_cannot_access_sow(): void
    {
        [$project] = $this->projectWithVendor();
        $hr = User::factory()->create(['role' => 'hr', 'is_active' => true]);

        $this->actingAs($hr)->get("/operational/projects/{$project->id}/sow")->assertForbidden();
    }
}
