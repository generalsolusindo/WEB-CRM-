<?php

namespace Tests\Feature\Operational;


use App\Models\Bast;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Sales\SalesOrderSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectExecutionTest extends TestCase
{
    use RefreshDatabase;

    private User $ops;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
    }

    public function test_start_moves_ready_to_in_progress(): void
    {
        $project = $this->readyProject();

        $this->actingAs(User::factory()->create(['role' => 'technician']))
            ->post("/operational/projects/{$project->id}/start")->assertForbidden();

        $this->actingAs($this->ops)->post("/operational/projects/{$project->id}/start")
            ->assertSessionHas('success');
        $this->assertSame('in_progress', $project->fresh()->status);
    }

    public function test_verify_bast_approve_completes_project_and_unlocks_final_invoice(): void
    {
        $project = $this->readyProject('mixed');
        $project->update(['status' => 'verification']);
        $bast = Bast::create([
            'project_id' => $project->id, 'status' => 'submitted',
            'submitted_by' => User::factory()->create(['role' => 'technician'])->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->ops)->put("/operational/projects/{$project->id}/bast/{$bast->id}", [
            'decision' => 'approve',
        ])->assertSessionHas('success');

        $this->assertSame('verified', $bast->fresh()->status);
        $this->assertSame('completed', $project->fresh()->status);
        $this->assertTrue(
            app(SalesOrderSettlement::class)->canCreateFinalInvoice($project->salesOrder->fresh()),
        );
    }

    public function test_verify_bast_reject_requires_notes_and_reworks_project(): void
    {
        $project = $this->readyProject();
        $project->update(['status' => 'verification']);
        $bast = Bast::create([
            'project_id' => $project->id, 'status' => 'submitted',
            'submitted_by' => User::factory()->create(['role' => 'technician'])->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->ops)->put("/operational/projects/{$project->id}/bast/{$bast->id}", [
            'decision' => 'reject',
        ])->assertSessionHasErrors('notes');

        $this->actingAs($this->ops)->put("/operational/projects/{$project->id}/bast/{$bast->id}", [
            'decision' => 'reject', 'notes' => 'Kabel belum rapi, ulang bagian panel.',
        ])->assertSessionHas('success');

        $this->assertSame('rejected', $bast->fresh()->status);
        $this->assertSame('in_progress', $project->fresh()->status);
    }

    public function test_only_operational_can_verify_bast(): void
    {
        $project = $this->readyProject();
        $project->update(['status' => 'verification']);
        $bast = Bast::create([
            'project_id' => $project->id, 'status' => 'submitted', 'submitted_at' => now(),
        ]);

        foreach (['technician', 'sales', 'finance'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->put("/operational/projects/{$project->id}/bast/{$bast->id}", ['decision' => 'approve'])
                ->assertForbidden();
        }
        $this->assertSame('submitted', $bast->fresh()->status);
    }

    public function test_material_only_completes_directly_without_bast(): void
    {
        $mixed = $this->readyProject('mixed');
        $mixed->update(['status' => 'in_progress']);
        $this->actingAs($this->ops)->post("/operational/projects/{$mixed->id}/complete")->assertForbidden();

        // Material Only sekarang skip teknisi/task/BAST sama sekali — readyProject()
        // meninggalkannya di WaitingResource begitu barang procurement diterima.
        Storage::fake('local');
        $material = $this->readyProject('material_only');
        $this->assertSame('waiting_resource', $material->status);

        // Belum ada Delivery Note sama sekali — belum boleh selesai.
        $this->actingAs($this->ops)->post("/operational/projects/{$material->id}/complete")->assertForbidden();

        $so = $material->salesOrder;
        $line = $so->lines()->first();
        $this->actingAs($this->ops)->post("/operational/sales-orders/{$so->id}/delivery-notes", [
            'delivery_method' => 'sendiri',
            'delivery_address' => 'Site A',
            'dispatch_proof' => UploadedFile::fake()->image('bukti.jpg'),
            'lines' => [['sales_order_line_id' => $line->id, 'qty_delivered' => $line->qty]],
        ]);

        $this->actingAs($this->ops)->post("/operational/projects/{$material->id}/complete")
            ->assertSessionHas('success');
        $this->assertSame('completed', $material->fresh()->status);
    }

    public function test_change_request_is_recorded_without_side_effects(): void
    {
        $project = $this->readyProject();
        $project->update(['status' => 'in_progress']);
        $tasksBefore = $project->tasks()->count();

        $this->actingAs($this->ops)->post("/operational/projects/{$project->id}/change-requests", [
            'type' => 'urgent_additional',
            'description' => 'Tambah 2 titik lampu di ruang server.',
        ])->assertSessionHas('success');

        $cr = $project->changeRequests()->firstOrFail();
        $this->assertSame('pending', $cr->status);
        $this->assertSame('in_progress', $project->fresh()->status);
        $this->assertSame($tasksBefore, $project->tasks()->count());

        $this->actingAs($this->ops)->put("/operational/projects/{$project->id}/change-requests/{$cr->id}", [
            'status' => 'approved',
        ])->assertSessionHas('success');
        $this->assertSame('approved', $cr->fresh()->status);
    }

    private function readyProject(string $orderType = 'mixed'): Project
    {
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
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload($orderType));
        $so = $quotation->salesOrder()->firstOrFail();

        $finance = User::factory()->create(['role' => 'finance']);
        $phase = $orderType === 'material_only' ? 'full' : 'dp';
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => $phase]);
        $invoice = $so->invoices()->latest('id')->firstOrFail();
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => (float) $invoice->amount + (float) $invoice->tax_amount,
            'paid_at' => now()->toDateTimeString(),
        ]);

        $project = Project::where('sales_order_id', $so->id)->firstOrFail();
        $project->update(['status' => 'planning']);

        $project->actualProcurements()->update([
            'cost_price' => 1000, 'is_paid' => true, 'status' => 'received', 'received_at' => now(),
        ]);

        if ($orderType === 'material_only') {
            // Material Only tidak lewat teknisi/task/markReady sama sekali.
            $project->update(['status' => 'waiting_resource']);

            return $project->fresh();
        }

        $tech = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $this->actingAs($this->ops)->post("/operational/projects/{$project->id}/tasks", ['title' => 'Instalasi']);
        $this->actingAs($this->ops)->put("/operational/projects/{$project->id}/technicians", [
            'technician_ids' => [$tech->id], 'leader_id' => $tech->id,
        ]);
        $this->actingAs($this->ops)->post("/operational/projects/{$project->id}/ready");

        return $project->fresh();
    }
}
