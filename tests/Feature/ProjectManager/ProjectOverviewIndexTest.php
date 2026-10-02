<?php

namespace Tests\Feature\ProjectManager;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectOverviewIndexTest extends TestCase
{
    use RefreshDatabase;

    /** Sama seperti daftar project Management: nama perusahaan harus ikut tampil, bukan cuma nama PIC. */
    public function test_project_list_shows_company_name_alongside_contact_name(): void
    {
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $project = $this->delegatedProject($pm);
        $contact = $project->salesOrder->contact;
        $contact->update(['company_name' => 'PT Mitra Jaya']);

        $this->actingAs($pm)->get('/project-manager/projects')
            ->assertInertia(fn ($page) => $page->where('projects.data.0.customer', "{$contact->name} · PT Mitra Jaya"));
    }

    private function delegatedProject(User $pm): Project
    {
        $manager = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust '.uniqid(), 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
            'delegated_to' => $pm->id, 'delegated_by' => $manager->id, 'delegated_at' => now(),
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'qty' => 2, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::with('lines')->latest('id')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines()->first()->id, 'selling_price' => 1300000]],
        ]);
        $quotation = Quotation::latest('id')->firstOrFail();
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

        return Project::where('sales_order_id', $so->id)->firstOrFail();
    }
}
