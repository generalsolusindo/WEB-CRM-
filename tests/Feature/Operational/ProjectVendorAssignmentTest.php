<?php

namespace Tests\Feature\Operational;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectVendorAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function project(): array
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

        return [$project, $ops];
    }

    public function test_operational_can_assign_and_clear_vendor(): void
    {
        [$project, $ops] = $this->project();
        $vendor = Vendor::create(['name' => 'Vendor A', 'provides_technical' => true]);

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/vendor", ['vendor_id' => $vendor->id])
            ->assertRedirect();
        $this->assertSame($vendor->id, $project->fresh()->vendor_id);

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/vendor", ['vendor_id' => ''])
            ->assertRedirect();
        $this->assertNull($project->fresh()->vendor_id);
    }

    public function test_cannot_assign_vendor_that_does_not_provide_technical(): void
    {
        [$project, $ops] = $this->project();
        $vendor = Vendor::create(['name' => 'Material Only Vendor', 'provides_technical' => false]);

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/vendor", ['vendor_id' => $vendor->id])
            ->assertSessionHasErrors('vendor_id');
    }

    public function test_non_operational_cannot_assign_vendor(): void
    {
        [$project] = $this->project();
        $vendor = Vendor::create(['name' => 'Vendor A', 'provides_technical' => true]);
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);

        $this->actingAs($management)->put("/operational/projects/{$project->id}/vendor", ['vendor_id' => $vendor->id])
            ->assertForbidden();
    }
}
