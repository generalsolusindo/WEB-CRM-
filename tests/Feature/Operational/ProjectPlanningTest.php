<?php

namespace Tests\Feature\Operational;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectPlanningTest extends TestCase
{
    use RefreshDatabase;

    private function operational(): User
    {
        return User::factory()->create(['role' => 'operational', 'is_active' => true]);
    }

    public function test_project_is_not_created_before_upfront_invoice_paid(): void
    {
        $so = $this->confirmedOrder('material_only');
        $finance = User::factory()->create(['role' => 'finance']);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'full']);

        // Invoice dibuat tapi belum dibayar -> belum ada project.
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_project_and_procurement_list_auto_generated_when_upfront_paid(): void
    {
        $so = $this->paidOrder('mixed');

        $project = Project::firstOrFail();
        $this->assertSame('draft', $project->status);
        $this->assertSame($so->id, $project->sales_order_id);
        // 1 line requirement -> 1 actual procurement item
        $this->assertSame(1, $project->actualProcurements()->count());
        $this->assertSame('pending', $project->actualProcurements()->value('status'));
    }

    public function test_planning_sets_dates_and_moves_draft_to_planning(): void
    {
        $this->paidOrder('mixed');
        $ops = $this->operational();
        $project = Project::firstOrFail();

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/planning", [
            'planned_start' => '2026-09-01',
            'planned_end' => '2026-09-10',
        ])->assertSessionHas('success');

        $project->refresh();
        $this->assertSame('planning', $project->status);
        $this->assertSame('2026-09-01', $project->planned_start->toDateString());

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/planning", [
            'planned_start' => '2026-09-10', 'planned_end' => '2026-09-01',
        ])->assertSessionHasErrors('planned_end');
    }

    public function test_non_operational_cannot_access_projects(): void
    {
        $this->paidOrder('mixed');

        foreach (['sales', 'finance', 'technician'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/operational/projects')->assertForbidden();
        }
    }

    private function paidOrder(string $orderType): SalesOrder
    {
        $so = $this->confirmedOrder($orderType);
        $finance = User::factory()->create(['role' => 'finance']);
        $phase = $orderType === 'material_only' ? 'full' : 'dp';
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => $phase]);
        $invoice = Invoice::firstOrFail();
        $grand = (float) $invoice->amount + (float) $invoice->tax_amount;
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => $grand, 'paid_at' => now()->toDateTimeString(),
        ]);

        return $so->fresh();
    }

    private function confirmedOrder(string $orderType): SalesOrder
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'qty' => 2, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines()->first()->id, 'selling_price' => 1300000]],
        ]);
        $quotation = Quotation::firstOrFail();
        $quotation->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload($orderType));

        return SalesOrder::firstOrFail();
    }
}
