<?php

namespace Tests\Feature\Operational;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BastDraftTest extends TestCase
{
    use RefreshDatabase;

    private function projectWithLead(): array
    {
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'address' => 'Jl. Contoh No. 1', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
            'pic_name' => 'Budi Santoso', 'pic_position' => 'Manager Operasional',
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
        $so->update(['po_number' => 'PO-001', 'po_date' => now()->toDateString()]);

        $finance = User::factory()->create(['role' => 'finance']);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'dp']);
        $invoice = $so->invoices()->latest('id')->firstOrFail();
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => (float) $invoice->amount + (float) $invoice->tax_amount,
            'paid_at' => now()->toDateTimeString(),
        ]);

        $project = Project::where('sales_order_id', $so->id)->firstOrFail();

        return [$project, $ops];
    }

    public function test_cannot_manage_bast_draft_before_team_assigned(): void
    {
        [$project, $ops] = $this->projectWithLead();

        $this->actingAs($ops)->get("/operational/projects/{$project->id}/bast-draft")->assertForbidden();
    }

    public function test_operational_generates_bast_draft_prefilled_from_lead_and_leader(): void
    {
        [$project, $ops] = $this->projectWithLead();
        $leader = User::factory()->create(['role' => 'technician', 'is_active' => true]);

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/technicians", [
            'technician_ids' => [$leader->id],
            'leader_id' => $leader->id,
        ]);

        $response = $this->actingAs($ops)->get("/operational/projects/{$project->id}/bast-draft");
        $response->assertOk();
        $draft = $response->viewData('page')['props']['draft'];
        $this->assertSame('Budi Santoso', $draft['pic_name']);
        $this->assertSame('Manager Operasional', $draft['pic_position']);
        $this->assertSame('Jl. Contoh No. 1', $draft['pic_address']);
        $this->assertSame($leader->name, $draft['leader_name']);

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/bast-draft", [
            'number' => 'BAST-001',
            'event_date' => now()->toDateString(),
            'job_title' => 'Instalasi PLTS 10 kWp',
            'work_description' => 'Instalasi panel surya dan inverter.',
            'pic_name' => 'Budi Santoso',
            'pic_position' => 'Manager Operasional',
            'pic_address' => 'Jl. Contoh No. 1',
            'leader_name' => $leader->name,
            'leader_position' => 'Teknisi',
        ])->assertRedirect();

        $this->assertDatabaseHas('bast_drafts', [
            'project_id' => $project->id,
            'number' => 'BAST-001',
            'job_title' => 'Instalasi PLTS 10 kWp',
        ]);

        $this->actingAs($ops)->get("/operational/projects/{$project->id}/bast-draft/print")
            ->assertOk()
            ->assertSee('Instalasi PLTS 10 kWp')
            ->assertSee('Budi Santoso');
    }

    public function test_bast_draft_can_be_edited_and_regenerated(): void
    {
        [$project, $ops] = $this->projectWithLead();
        $leader = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $this->actingAs($ops)->put("/operational/projects/{$project->id}/technicians", [
            'technician_ids' => [$leader->id],
            'leader_id' => $leader->id,
        ]);

        $payload = [
            'number' => 'BAST-001', 'event_date' => now()->toDateString(), 'job_title' => 'Awal',
            'work_description' => null, 'pic_name' => 'Budi', 'pic_position' => null,
            'pic_address' => null, 'leader_name' => $leader->name, 'leader_position' => null,
        ];
        $this->actingAs($ops)->put("/operational/projects/{$project->id}/bast-draft", $payload);
        $this->actingAs($ops)->put("/operational/projects/{$project->id}/bast-draft", [...$payload, 'job_title' => 'Sudah Direvisi']);

        $this->assertDatabaseCount('bast_drafts', 1);
        $this->assertDatabaseHas('bast_drafts', ['project_id' => $project->id, 'job_title' => 'Sudah Direvisi']);
    }

    public function test_other_roles_cannot_access_bast_draft(): void
    {
        [$project] = $this->projectWithLead();
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);

        $this->actingAs($management)->get("/operational/projects/{$project->id}/bast-draft")->assertForbidden();
    }
}
