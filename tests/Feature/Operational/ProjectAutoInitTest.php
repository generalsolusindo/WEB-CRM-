<?php

namespace Tests\Feature\Operational;

use App\Actions\Operational\InitializeProject;
use App\Models\ActualProcurement;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAutoInitTest extends TestCase
{
    use RefreshDatabase;

    public function test_upfront_paid_generates_project_and_notifies_procurement(): void
    {
        $proc = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        User::factory()->create(['role' => 'procurement', 'is_active' => false]);

        $so = $this->paidOrder();

        $project = Project::firstOrFail();
        $this->assertSame('draft', $project->status);
        $this->assertNull($project->created_by);

        $items = $project->actualProcurements;
        $this->assertCount(2, $items); // 2 requirement lines
        $this->assertTrue($items->every(fn ($i) => $i->status === 'pending' && $i->requested_by === null));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $proc->id,
            'type' => 'project_procurement.requested',
        ]);
        $this->assertSame(1, Notification::where('type', 'project_procurement.requested')->count());
    }

    public function test_initialize_project_is_idempotent(): void
    {
        $so = $this->paidOrder();
        $action = app(InitializeProject::class);

        $action->handle($so->fresh());
        $action->handle($so->fresh());

        $this->assertSame(1, Project::count());
        $this->assertSame(2, ActualProcurement::count());
    }

    private function paidOrder(): SalesOrder
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'qty' => 2, 'unit' => 'unit', 'created_by' => $sales->id]);
        $lead->requirements()->create(['item_name' => 'Switch', 'qty' => 1, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $lineIds = $pr->lines()->pluck('id');
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => $lineIds->map(fn ($id) => ['procurement_request_line_id' => $id, 'selling_price' => 1300000])->all(),
        ]);
        $quotation = Quotation::firstOrFail();
        $quotation->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload("mixed"));
        $so = SalesOrder::firstOrFail();

        $finance = User::factory()->create(['role' => 'finance']);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'dp']);
        $invoice = Invoice::firstOrFail();
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => (float) $invoice->amount + (float) $invoice->tax_amount,
            'paid_at' => now()->toDateTimeString(),
        ]);

        return $so->fresh();
    }
}
