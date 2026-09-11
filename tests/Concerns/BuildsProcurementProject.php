<?php

namespace Tests\Concerns;

use App\Actions\Finance\RecordProcurementPayment;
use App\Actions\Procurement\ConfirmProcurementPayment;
use App\Actions\Procurement\ReceiveProcurementItem;
use App\Actions\Procurement\ReviewProcurementPayment;
use App\Actions\Procurement\SubmitProcurementPayment;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Vendor;

trait BuildsProcurementProject
{
    /**
     * Bangun sebuah Project berstatus Planning yang punya baris pengadaan
     * material ({@see ActualProcurement}) hasil seeding dari Procurement Request.
     *
     * @param  array<int, array{item_name?: string, qty?: int|float, unit?: string, cost_price?: int|float}>  $items
     */
    protected function materialProject(array $items = []): Project
    {
        $items = $items ?: [['item_name' => 'Router', 'qty' => 2, 'unit' => 'unit', 'cost_price' => 1000000]];

        $sales = User::factory()->create(['role' => 'sales']);
        $manager = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
            'delegated_to' => $pm->id, 'delegated_by' => $manager->id, 'delegated_at' => now(),
        ]);

        foreach ($items as $row) {
            $lead->requirements()->create([
                'item_name' => $row['item_name'] ?? 'Router',
                'qty' => $row['qty'] ?? 2,
                'unit' => $row['unit'] ?? 'unit',
                'created_by' => $sales->id,
            ]);
        }

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::with('lines')->firstOrFail();

        foreach ($pr->lines as $i => $line) {
            $line->update([
                'cost_price' => $items[$i]['cost_price'] ?? 1000000,
                'availability_status' => 'available',
            ]);
        }
        $pr->update(['status' => 'ready']);

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => $pr->lines->map(fn ($line) => [
                'procurement_request_line_id' => $line->id,
                'selling_price' => (float) $line->cost_price * 1.3,
            ])->all(),
        ]);
        $quotation = Quotation::firstOrFail();
        $quotation->update([
            'status' => 'sent',
            'pm_review_status' => 'approved',
            'manager_review_status' => 'approved',
        ]);

        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload('material_only'));
        $so = SalesOrder::firstOrFail();

        $finance = User::factory()->create(['role' => 'finance']);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'full']);
        $invoice = Invoice::firstOrFail();
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => (float) $invoice->amount + (float) $invoice->tax_amount,
            'paid_at' => now()->toDateTimeString(),
        ]);

        $project = Project::firstOrFail();
        $project->update(['status' => 'planning']);

        return $project->fresh();
    }

    /**
     * Jalankan alur pengadaan penuh: sourcing → PM approve → Finance bayar →
     * Procurement konfirmasi → semua item diterima.
     */
    protected function settleProcurement(Project $project): void
    {
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $pm = User::find($project->delegated_to);
        $vendor = Vendor::firstOrCreate(['name' => 'PT Vendor Uji']);

        $project->actualProcurements()
            ->where('from_office_stock', false)
            ->update(['vendor_id' => $vendor->id, 'cost_price' => 100000]);

        $payment = app(SubmitProcurementPayment::class)->handle($project->fresh(), $procurement, ['pricing_mode' => 'itemized']);
        app(ReviewProcurementPayment::class)->handle($payment, $pm, true, null);
        app(RecordProcurementPayment::class)->handle($payment->fresh(), $finance, [
            'item_ids' => $project->actualProcurements()->where('from_office_stock', false)->pluck('id')->all(),
            'proof' => \Illuminate\Http\UploadedFile::fake()->create('tf.pdf', 20, 'application/pdf'),
        ]);
        app(ConfirmProcurementPayment::class)->handle($payment->fresh(), $procurement);

        foreach ($project->actualProcurements()->get() as $item) {
            if ($item->status !== 'received') {
                app(ReceiveProcurementItem::class)->handle($item, $procurement);
            }
        }
    }
}
