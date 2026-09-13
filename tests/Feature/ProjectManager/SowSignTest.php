<?php

namespace Tests\Feature\ProjectManager;

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

class SowSignTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSignature(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }

    private function pendingDirectorSignatureSow(?User $pm = null): Sow
    {
        Storage::fake('local');
        User::factory()->create([
            'role' => 'administrator', 'is_active' => true,
            'signature_path' => UploadedFile::fake()->image('sig.png')->store('administrator-signatures'),
        ]);

        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified']);
        $lead->requirements()->create(['item_name' => 'ODP', 'qty' => 2, 'unit' => 'unit', 'created_by' => $sales->id]);
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
        if ($pm) {
            $project->update(['delegated_to' => $pm->id]);
        }
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
        $this->actingAs($technician)->post("/technician/sows/{$sow->id}/sign", ['signature' => $this->fakeSignature()]);
        $this->actingAs($vendorUser)->post("/vendor/sows/{$sow->id}/sign", ['signature' => $this->fakeSignature()]);
        $this->actingAs($hr)->post("/hr/sows/{$sow->id}/verify-signatures", ['approved' => true]);
        $this->actingAs($ops)->post("/operational/sows/{$sow->id}/sign-operational");

        return $sow->fresh();
    }

    public function test_delegated_project_manager_can_sign_final_step(): void
    {
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $otherPm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $sow = $this->pendingDirectorSignatureSow($pm);

        $this->assertSame('pending_director_signature', $sow->status);

        $this->actingAs($otherPm)->post("/project-manager/sows/{$sow->id}/sign")->assertForbidden();
        $this->actingAs($otherPm)->get("/project-manager/sows/{$sow->id}")->assertForbidden();

        $this->actingAs($pm)->get('/project-manager/sows')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('sows.data', 1));

        $this->actingAs($pm)->post("/project-manager/sows/{$sow->id}/sign")->assertRedirect();

        $sow->refresh();
        $this->assertSame('completed', $sow->status);
        $this->assertNotNull($sow->director_signature);
    }

    public function test_management_signs_as_fallback_when_project_not_delegated(): void
    {
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $sow = $this->pendingDirectorSignatureSow(null);

        $this->actingAs($pm)->post("/project-manager/sows/{$sow->id}/sign")->assertForbidden();

        $this->actingAs($management)->get('/management/sows')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('sows.data', 1));

        $this->actingAs($management)->post("/management/sows/{$sow->id}/sign")->assertRedirect();

        $sow->refresh();
        $this->assertSame('completed', $sow->status);
    }

    public function test_management_cannot_sign_once_project_is_delegated(): void
    {
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $sow = $this->pendingDirectorSignatureSow($pm);

        $this->actingAs($management)->get('/management/sows')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('sows.data', 0));

        $this->actingAs($management)->post("/management/sows/{$sow->id}/sign")->assertForbidden();
    }
}
