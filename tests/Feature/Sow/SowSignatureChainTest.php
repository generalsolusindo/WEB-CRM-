<?php

namespace Tests\Feature\Sow;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Sow;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SowSignatureChainTest extends TestCase
{
    use RefreshDatabase;

    private function administratorWithSignature(): User
    {
        Storage::fake('local');

        return User::factory()->create([
            'role' => 'administrator',
            'is_active' => true,
            'signature_path' => UploadedFile::fake()->image('sig.png')->store('administrator-signatures'),
        ]);
    }

    private function readyForSignature(): array
    {
        $this->administratorWithSignature();
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
        $vendor = Vendor::create(['name' => 'Vendor A', 'provides_technical' => true, 'contact_person' => 'PIC A', 'phone' => '0811']);
        $project->update(['vendor_id' => $vendor->id]);
        $technician = User::factory()->create(['role' => 'technician', 'vendor_id' => $vendor->id, 'is_active' => true]);
        $vendorUser = User::factory()->create(['role' => 'vendor', 'vendor_id' => $vendor->id, 'is_active' => true]);
        $hr = User::factory()->create(['role' => 'hr', 'is_active' => true]);
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", [
            'number' => 'SOW-001', 'project_name' => 'Jasa X', 'technician_id' => $technician->id,
        ]);
        $this->actingAs($ops)->post("/operational/projects/{$project->id}/sow/submit");
        $sow = Sow::where('project_id', $project->id)->firstOrFail();
        $this->actingAs($hr)->post("/hr/sows/{$sow->id}/review", ['approved' => true]);

        return compact('sow', 'ops', 'vendor', 'technician', 'vendorUser', 'hr', 'management');
    }

    private function fakeSignature(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }

    public function test_full_signature_chain_completes_sow(): void
    {
        ['sow' => $sow, 'ops' => $ops, 'technician' => $technician, 'vendorUser' => $vendorUser, 'hr' => $hr, 'management' => $management] = $this->readyForSignature();

        $this->assertSame('pending_technician_signature', $sow->fresh()->status);

        $this->actingAs($technician)->post("/technician/sows/{$sow->id}/sign", ['signature' => $this->fakeSignature()])
            ->assertRedirect();
        $sow->refresh();
        $this->assertSame('pending_vendor_signature', $sow->status);
        $this->assertNotNull($sow->technician_signature);

        $this->assertDatabaseHas('notifications', ['user_id' => $vendorUser->id, 'type' => 'sow.pending_vendor_signature']);

        $this->actingAs($vendorUser)->post("/vendor/sows/{$sow->id}/sign", ['signature' => $this->fakeSignature()])
            ->assertRedirect();
        $sow->refresh();
        $this->assertSame('pending_hr_verification', $sow->status);
        $this->assertSame($vendorUser->id, $sow->vendor_signed_by);

        $this->actingAs($hr)->post("/hr/sows/{$sow->id}/verify-signatures", ['approved' => true])->assertRedirect();
        $sow->refresh();
        $this->assertSame('pending_admin_signature', $sow->status);

        $this->actingAs($ops)->post("/operational/sows/{$sow->id}/sign-operational", ['signature' => $this->fakeSignature()])
            ->assertRedirect();
        $sow->refresh();
        $this->assertSame('pending_director_signature', $sow->status);

        $this->assertDatabaseHas('notifications', ['user_id' => $management->id, 'type' => 'sow.pending_director_signature']);

        $this->actingAs($management)->post("/management/sows/{$sow->id}/sign", ['signature' => $this->fakeSignature()])
            ->assertRedirect();
        $sow->refresh();
        $this->assertSame('completed', $sow->status);
        $this->assertNotNull($sow->director_signature);
    }

    public function test_bell_notifications_auto_clear_as_each_signer_acts_even_without_being_clicked(): void
    {
        ['sow' => $sow, 'ops' => $ops, 'technician' => $technician, 'vendorUser' => $vendorUser, 'hr' => $hr, 'management' => $management] = $this->readyForSignature();

        // Teknisi punya notifikasi "perlu TTD" yang belum pernah diklik.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $technician->id, 'type' => 'sow.pending_technician_signature', 'read_at' => null,
        ]);

        $this->actingAs($technician)->post("/technician/sows/{$sow->id}/sign", ['signature' => $this->fakeSignature()]);

        // Begitu teknisi TTD, notifikasi lamanya otomatis clear meski tak pernah diklik —
        // dan notifikasi baru untuk vendor muncul.
        $this->assertNotNull(
            Notification::where(['user_id' => $technician->id, 'type' => 'sow.pending_technician_signature'])->first()->read_at,
        );
        $this->assertDatabaseHas('notifications', [
            'user_id' => $vendorUser->id, 'type' => 'sow.pending_vendor_signature', 'read_at' => null,
        ]);

        $this->actingAs($vendorUser)->post("/vendor/sows/{$sow->id}/sign", ['signature' => $this->fakeSignature()]);

        $this->assertNotNull(
            Notification::where(['user_id' => $vendorUser->id, 'type' => 'sow.pending_vendor_signature'])->first()->read_at,
        );
        $this->assertDatabaseHas('notifications', [
            'user_id' => $hr->id, 'type' => 'sow.pending_hr_verification', 'read_at' => null,
        ]);

        $this->actingAs($hr)->post("/hr/sows/{$sow->id}/verify-signatures", ['approved' => true]);

        $this->assertNotNull(
            Notification::where(['user_id' => $hr->id, 'type' => 'sow.pending_hr_verification'])->first()->read_at,
        );
        $this->assertDatabaseHas('notifications', [
            'user_id' => $ops->id, 'type' => 'sow.pending_admin_signature', 'read_at' => null,
        ]);

        $this->actingAs($ops)->post("/operational/sows/{$sow->id}/sign-operational", ['signature' => $this->fakeSignature()]);

        $this->assertNotNull(
            Notification::where(['user_id' => $ops->id, 'type' => 'sow.pending_admin_signature'])->first()->read_at,
        );
        $this->assertDatabaseHas('notifications', [
            'user_id' => $management->id, 'type' => 'sow.pending_director_signature', 'read_at' => null,
        ]);

        $this->actingAs($management)->post("/management/sows/{$sow->id}/sign", ['signature' => $this->fakeSignature()]);

        $this->assertNotNull(
            Notification::where(['user_id' => $management->id, 'type' => 'sow.pending_director_signature'])->first()->read_at,
        );
    }

    public function test_only_the_designated_technician_can_sign(): void
    {
        ['sow' => $sow, 'vendor' => $vendor] = $this->readyForSignature();
        $otherTechnician = User::factory()->create(['role' => 'technician', 'vendor_id' => $vendor->id, 'is_active' => true]);

        $this->actingAs($otherTechnician)->post("/technician/sows/{$sow->id}/sign", ['signature' => $this->fakeSignature()])
            ->assertForbidden();
    }

    public function test_only_vendor_matching_project_can_sign(): void
    {
        ['sow' => $sow, 'technician' => $technician] = $this->readyForSignature();
        $this->actingAs($technician)->post("/technician/sows/{$sow->id}/sign", ['signature' => $this->fakeSignature()]);

        $otherVendor = Vendor::create(['name' => 'Vendor B', 'provides_technical' => true]);
        $otherVendorUser = User::factory()->create(['role' => 'vendor', 'vendor_id' => $otherVendor->id, 'is_active' => true]);

        $this->actingAs($otherVendorUser)->post("/vendor/sows/{$sow->id}/sign", ['signature' => $this->fakeSignature()])
            ->assertForbidden();
    }

    public function test_hr_rejecting_signatures_lets_operational_restart_and_technician_resigns(): void
    {
        ['sow' => $sow, 'ops' => $ops, 'technician' => $technician, 'vendorUser' => $vendorUser, 'hr' => $hr] = $this->readyForSignature();

        $this->actingAs($technician)->post("/technician/sows/{$sow->id}/sign", ['signature' => $this->fakeSignature()]);
        $this->actingAs($vendorUser)->post("/vendor/sows/{$sow->id}/sign", ['signature' => $this->fakeSignature()]);

        $this->actingAs($hr)->post("/hr/sows/{$sow->id}/verify-signatures", [
            'approved' => false, 'notes' => 'Tanda tangan teknisi tidak jelas',
        ])->assertRedirect();

        $sow->refresh();
        $this->assertSame('rejected_signature', $sow->status);

        $this->assertDatabaseHas('notifications', ['user_id' => $ops->id, 'type' => 'sow.rejected_signature']);

        $this->actingAs($ops)->post("/operational/sows/{$sow->id}/restart-signatures")->assertRedirect();
        $sow->refresh();
        $this->assertSame('pending_technician_signature', $sow->status);
        $this->assertNull($sow->technician_signature);
        $this->assertNull($sow->vendor_signature);

        $this->actingAs($technician)->post("/technician/sows/{$sow->id}/sign", ['signature' => $this->fakeSignature()])
            ->assertRedirect();
        $this->assertSame('pending_vendor_signature', $sow->fresh()->status);
    }

    public function test_reject_signature_verification_requires_notes(): void
    {
        ['sow' => $sow, 'technician' => $technician, 'vendorUser' => $vendorUser, 'hr' => $hr] = $this->readyForSignature();
        $this->actingAs($technician)->post("/technician/sows/{$sow->id}/sign", ['signature' => $this->fakeSignature()]);
        $this->actingAs($vendorUser)->post("/vendor/sows/{$sow->id}/sign", ['signature' => $this->fakeSignature()]);

        $this->actingAs($hr)->post("/hr/sows/{$sow->id}/verify-signatures", ['approved' => false])
            ->assertSessionHasErrors('notes');
    }

    public function test_cannot_reassign_project_vendor_once_sow_is_submitted(): void
    {
        ['sow' => $sow, 'ops' => $ops] = $this->readyForSignature();
        $project = $sow->project;
        $otherVendor = Vendor::create(['name' => 'Vendor B', 'provides_technical' => true]);

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/vendor", ['vendor_id' => $otherVendor->id])
            ->assertForbidden();
        $this->actingAs($ops)->put("/operational/projects/{$project->id}/vendor", ['vendor_id' => ''])
            ->assertForbidden();
    }

    public function test_changing_vendor_while_draft_clears_stale_technician(): void
    {
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified']);
        $lead->requirements()->create(['item_name' => 'ODP', 'qty' => 1, 'unit' => 'unit', 'created_by' => $sales->id]);
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
            'amount_paid' => (float) $invoice->amount + (float) $invoice->tax_amount, 'paid_at' => now()->toDateTimeString(),
        ]);
        $project = Project::where('sales_order_id', $so->id)->firstOrFail();
        $vendorA = Vendor::create(['name' => 'Vendor A', 'provides_technical' => true]);
        $project->update(['vendor_id' => $vendorA->id]);
        $technicianA = User::factory()->create(['role' => 'technician', 'vendor_id' => $vendorA->id, 'is_active' => true]);

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", [
            'number' => 'SOW-001', 'project_name' => 'Jasa X', 'technician_id' => $technicianA->id,
        ]);

        $vendorB = Vendor::create(['name' => 'Vendor B', 'provides_technical' => true]);
        $this->actingAs($ops)->put("/operational/projects/{$project->id}/vendor", ['vendor_id' => $vendorB->id])
            ->assertRedirect();

        $sow = Sow::where('project_id', $project->id)->firstOrFail();
        $this->assertNull($sow->technician_id);
    }

    public function test_technician_and_vendor_cannot_see_sow_before_their_stage(): void
    {
        // Bangun sampai draft (belum submit ke HR).
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified']);
        $lead->requirements()->create(['item_name' => 'ODP', 'qty' => 1, 'unit' => 'unit', 'created_by' => $sales->id]);
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
            'amount_paid' => (float) $invoice->amount + (float) $invoice->tax_amount, 'paid_at' => now()->toDateTimeString(),
        ]);
        $project = Project::where('sales_order_id', $so->id)->firstOrFail();
        $vendor = Vendor::create(['name' => 'Vendor A', 'provides_technical' => true]);
        $project->update(['vendor_id' => $vendor->id]);
        $technician = User::factory()->create(['role' => 'technician', 'vendor_id' => $vendor->id, 'is_active' => true]);
        $vendorUser = User::factory()->create(['role' => 'vendor', 'vendor_id' => $vendor->id, 'is_active' => true]);

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", [
            'number' => 'SOW-001', 'project_name' => 'Jasa X', 'technician_id' => $technician->id,
        ]);
        $sow = Sow::where('project_id', $project->id)->firstOrFail();

        // Draft: teknisi & vendor belum boleh lihat.
        $this->actingAs($technician)->get("/technician/sows/{$sow->id}")->assertForbidden();
        $this->actingAs($vendorUser)->get("/vendor/sows/{$sow->id}")->assertForbidden();
        $this->assertCount(0, $this->actingAs($technician)->get('/technician/sows')->viewData('page')['props']['sows']['data']);

        // Setelah HR setujui isi → teknisi boleh lihat, vendor belum.
        $this->actingAs($ops)->post("/operational/projects/{$project->id}/sow/submit");
        $hr = User::factory()->create(['role' => 'hr', 'is_active' => true]);
        $this->actingAs($hr)->post("/hr/sows/{$sow->id}/review", ['approved' => true]);

        $this->actingAs($technician)->get("/technician/sows/{$sow->id}")->assertOk();
        $this->actingAs($vendorUser)->get("/vendor/sows/{$sow->id}")->assertForbidden();

        // Setelah teknisi TTD → vendor boleh lihat.
        $this->actingAs($technician)->post("/technician/sows/{$sow->id}/sign", ['signature' => $this->fakeSignature()]);
        $this->actingAs($vendorUser)->get("/vendor/sows/{$sow->id}")->assertOk();
    }

    public function test_cannot_skip_signature_order(): void
    {
        ['sow' => $sow, 'vendorUser' => $vendorUser, 'ops' => $ops, 'management' => $management] = $this->readyForSignature();

        $this->actingAs($vendorUser)->post("/vendor/sows/{$sow->id}/sign", ['signature' => $this->fakeSignature()])
            ->assertForbidden();
        $this->actingAs($ops)->post("/operational/sows/{$sow->id}/sign-operational", ['signature' => $this->fakeSignature()])
            ->assertForbidden();
        $this->actingAs($management)->post("/management/sows/{$sow->id}/sign", ['signature' => $this->fakeSignature()])
            ->assertForbidden();
    }
}
