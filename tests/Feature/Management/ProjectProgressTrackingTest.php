<?php

namespace Tests\Feature\Management;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\ProjectStatusHistory;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectProgressTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_changes_are_logged_automatically_with_who_changed_it(): void
    {
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $project = $this->plannedProject();
        $before = ProjectStatusHistory::where('project_id', $project->id)->count();

        $this->actingAs($management);
        $project->update(['status' => 'ready']);

        $this->actingAs($ops)->post("/operational/projects/{$project->id}/start")
            ->assertSessionHas('success');

        $histories = ProjectStatusHistory::where('project_id', $project->id)->orderBy('id')->get()->slice($before)->values();
        $this->assertCount(2, $histories);

        $this->assertSame('planning', $histories[0]->from_status);
        $this->assertSame('ready', $histories[0]->to_status);
        $this->assertSame($management->id, $histories[0]->changed_by);

        $this->assertSame('ready', $histories[1]->from_status);
        $this->assertSame('in_progress', $histories[1]->to_status);
        $this->assertSame($ops->id, $histories[1]->changed_by);
    }

    public function test_history_is_removed_when_project_is_deleted(): void
    {
        $project = $this->plannedProject();
        $project->update(['status' => 'ready']);
        $this->assertTrue(ProjectStatusHistory::where('project_id', $project->id)->exists());

        $project->delete();

        $this->assertDatabaseMissing('project_status_histories', ['project_id' => $project->id]);
    }

    public function test_overview_page_exposes_target_dates_history_and_invoices(): void
    {
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $project = $this->plannedProject();
        $project->update(['planned_start' => '2026-01-01', 'planned_end' => '2020-01-01']);
        $project->update(['status' => 'ready']);
        $historyCount = ProjectStatusHistory::where('project_id', $project->id)->count();

        $res = $this->actingAs($management)->get("/management/projects/{$project->id}");
        $res->assertOk();
        $res->assertInertia(fn ($page) => $page
            ->where('project.planned_start', '2026-01-01')
            ->where('project.planned_end', '2020-01-01')
            ->where('project.is_overdue', true)
            ->has('project.status_histories', $historyCount)
            ->where("project.status_histories.{$this->lastIndex($historyCount)}.from_status", 'planning')
            ->where("project.status_histories.{$this->lastIndex($historyCount)}.to_status", 'ready')
            ->has('project.invoices', 1)
            ->where('project.invoices.0.status', 'paid'));

        // Project selesai tidak dianggap terlambat lagi meski planned_end sudah lewat.
        $project->update(['status' => 'completed']);
        $res2 = $this->actingAs($management)->get("/management/projects/{$project->id}");
        $res2->assertInertia(fn ($page) => $page->where('project.is_overdue', false));
    }

    private function lastIndex(int $count): int
    {
        return $count - 1;
    }

    private function plannedProject(): Project
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust '.uniqid(), 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
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
        $invoice->update(['status' => 'paid']);

        $project = Project::where('sales_order_id', $so->id)->firstOrFail();
        $project->update(['status' => 'planning']);

        return $project->fresh();
    }
}
