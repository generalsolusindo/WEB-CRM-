<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\Sow;
use App\Models\Survey;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regresi: menu Survey di sidebar Procurement/Operational/Finance, SOW di sidebar
 * Project Manager, dan menu tambahan vendor yang merangkap teknisi/surveyor —
 * semuanya dulu tidak pernah menampilkan badge merah sama sekali walau ada
 * pekerjaan yang menunggu, karena MenuBadges belum menghitungnya.
 */
class MenuBadgesTest extends TestCase
{
    use RefreshDatabase;

    private function badges(User $user): array
    {
        return $this->actingAs($user)->get('/dashboard')
            ->viewData('page')['props']['auth']['menuBadges'];
    }

    private function surveyForLead(User $sales): Survey
    {
        $contact = Contact::create(['name' => 'Customer Survey', 'created_by' => $sales->id]);
        $lead = Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified']);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/surveys", [
            'site_address' => 'Jl. Proyek No. 1', 'site_region' => 'Balikpapan',
            'delivery_mode' => 'internal', 'billable' => false,
        ]);

        return Survey::latest('id')->firstOrFail();
    }

    public function test_procurement_sees_survey_badge_only_for_newly_requested_surveys(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $survey = $this->surveyForLead($sales);
        $this->assertSame('requested', $survey->status);

        $this->assertSame(1, $this->badges($procurement)['/procurement/surveys']);

        $survey->update(['status' => 'finance_review']);
        $this->assertSame(0, $this->badges($procurement)['/procurement/surveys']);
    }

    public function test_finance_sees_survey_badge_for_billing_stages_only(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $survey = $this->surveyForLead($sales);

        $this->assertSame(0, $this->badges($finance)['/finance/surveys']);

        $survey->update(['status' => 'finance_review']);
        $this->assertSame(1, $this->badges($finance)['/finance/surveys']);

        $survey->update(['status' => 'awaiting_payment']);
        $this->assertSame(1, $this->badges($finance)['/finance/surveys']);

        $survey->update(['status' => 'awaiting_briefing']);
        $this->assertSame(0, $this->badges($finance)['/finance/surveys']);
    }

    public function test_operational_sees_survey_badge_matching_its_own_queue(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $operational = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $survey = $this->surveyForLead($sales);

        $this->assertSame(0, $this->badges($operational)['/operational/surveys']);

        foreach (['awaiting_briefing', 'in_progress', 'report_review'] as $status) {
            $survey->update(['status' => $status]);
            $this->assertSame(1, $this->badges($operational)['/operational/surveys'], "status={$status}");
        }

        $survey->update(['status' => 'verified']);
        $this->assertSame(0, $this->badges($operational)['/operational/surveys']);
    }

    /** @return array{User, Sow} */
    private function pendingDirectorSow(): array
    {
        Storage::fake('local');
        $sales = User::factory()->create(['role' => 'sales']);
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified']);
        $lead->requirements()->create(['item_name' => 'ODP', 'qty' => 2, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines()->first()->id, 'selling_price' => 1300000]],
        ]);
        $quotation = Quotation::firstOrFail();
        $quotation->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload('mixed'));
        $so = SalesOrder::firstOrFail();

        $finance = User::factory()->create(['role' => 'finance']);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'dp']);
        $invoice = $so->invoices()->latest('id')->firstOrFail();
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => (float) $invoice->amount + (float) $invoice->tax_amount,
            'paid_at' => now()->toDateTimeString(),
        ]);

        $project = Project::where('sales_order_id', $so->id)->firstOrFail();
        $project->update(['delegated_to' => $pm->id]);
        $vendor = Vendor::create(['name' => 'Vendor A', 'provides_technical' => true, 'contact_person' => 'PIC A', 'phone' => '0811']);
        $project->update(['vendor_id' => $vendor->id]);
        $technician = User::factory()->create(['role' => 'technician', 'vendor_id' => $vendor->id, 'is_active' => true]);
        $vendorUser = User::factory()->create(['role' => 'vendor', 'vendor_id' => $vendor->id, 'is_active' => true]);
        $hr = User::factory()->create(['role' => 'hr', 'is_active' => true]);

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", [
            'number' => 'SOW-001', 'project_name' => 'Jasa X', 'technician_id' => $technician->id,
        ]);
        $this->actingAs($ops)->post("/operational/projects/{$project->id}/sow/submit");
        $sow = Sow::where('project_id', $project->id)->firstOrFail();
        $this->actingAs($hr)->post("/hr/sows/{$sow->id}/review", ['approved' => true]);
        $this->actingAs($technician)->post("/technician/sows/{$sow->id}/sign", [
            'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        ]);
        $this->actingAs($vendorUser)->post("/vendor/sows/{$sow->id}/sign", [
            'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        ]);
        $this->actingAs($hr)->post("/hr/sows/{$sow->id}/verify-signatures", ['approved' => true]);
        $ops->update(['signature_path' => UploadedFile::fake()->image('sig.png')->store('user-signatures')]);
        $this->actingAs($ops)->post("/operational/sows/{$sow->id}/sign-operational");

        return [$pm, $sow->fresh()];
    }

    public function test_project_manager_sees_sow_badge_matching_its_own_queue(): void
    {
        [$pm, $sow] = $this->pendingDirectorSow();
        $this->assertSame('pending_director_signature', $sow->status);

        $this->assertSame(1, $this->badges($pm)['/project-manager/sows']);

        $other = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $this->assertSame(0, $this->badges($other)['/project-manager/sows']);
    }

    public function test_vendor_who_also_works_as_technician_gets_technician_task_badge(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor B', 'provides_technical' => true]);
        $vendorUser = User::factory()->create(['role' => 'vendor', 'vendor_id' => $vendor->id, 'is_active' => true, 'can_technician' => true]);
        $plainVendorUser = User::factory()->create(['role' => 'vendor', 'vendor_id' => $vendor->id, 'is_active' => true]);

        $badges = $this->badges($vendorUser);
        $this->assertArrayHasKey('/technician/tasks', $badges);

        $this->assertArrayNotHasKey('/technician/tasks', $this->badges($plainVendorUser));
    }

    public function test_vendor_who_also_works_as_surveyor_gets_survey_badge(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor C', 'provides_technical' => true]);
        $vendorUser = User::factory()->create(['role' => 'vendor', 'vendor_id' => $vendor->id, 'is_active' => true, 'can_surveyor' => true]);

        $badges = $this->badges($vendorUser);
        $this->assertArrayHasKey('/technician/surveys', $badges);
    }
}
