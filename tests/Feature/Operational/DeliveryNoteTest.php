<?php

namespace Tests\Feature\Operational;

use App\Models\Contact;
use App\Models\DeliveryNote;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeliveryNoteTest extends TestCase
{
    use RefreshDatabase;

    /** SO campuran: 2 baris material (Router qty 2, Switch qty 4) + 1 baris jasa. */
    private function mixedSalesOrder(): SalesOrder
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'address' => 'Jl. Default No. 1', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'category' => 'material', 'qty' => 2, 'unit' => 'unit', 'created_by' => $sales->id]);
        $lead->requirements()->create(['item_name' => 'Switch', 'category' => 'material', 'qty' => 4, 'unit' => 'unit', 'created_by' => $sales->id]);
        $lead->requirements()->create(['item_name' => 'Jasa Instalasi', 'category' => 'service', 'qty' => 1, 'unit' => 'lot', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $pr->lines()->update(['cost_price' => 100000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $lines = $pr->lines->sortBy('id')->values();

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [
                ['procurement_request_line_id' => $lines[0]->id, 'selling_price' => 150000],
                ['procurement_request_line_id' => $lines[1]->id, 'selling_price' => 150000],
                ['procurement_request_line_id' => $lines[2]->id, 'selling_price' => 500000],
            ],
        ]);
        $q = Quotation::firstOrFail();
        $q->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$q->id}/confirm", array_merge(
            $this->confirmPayload('mixed'),
            ['po_number' => 'PO/2026/001', 'po_date' => '2026-08-01'],
        ));

        return SalesOrder::with('lines')->firstOrFail();
    }

    public function test_po_date_is_saved_at_confirm_deal(): void
    {
        $so = $this->mixedSalesOrder();
        $this->assertSame('PO/2026/001', $so->po_number);
        $this->assertSame('2026-08-01', $so->po_date->format('Y-m-d'));
    }

    public function test_operational_creates_delivery_note_with_material_lines_only(): void
    {
        Storage::fake('local');
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $so = $this->mixedSalesOrder();
        $router = $so->lines->firstWhere('item_name', 'Router');
        $switch = $so->lines->firstWhere('item_name', 'Switch');

        $res = $this->actingAs($ops)->post("/operational/sales-orders/{$so->id}/delivery-notes", [
            'delivery_method' => 'ekspedisi',
            'delivery_address' => 'Site A, Jakarta',
            'shipper_name' => 'Budi',
            'tracking_number' => 'JNE123456',
            'approved_by_name' => 'Rina',
            'dispatch_proof' => UploadedFile::fake()->image('resi.jpg'),
            'lines' => [
                ['sales_order_line_id' => $router->id, 'qty_delivered' => 2],
                ['sales_order_line_id' => $switch->id, 'qty_delivered' => 1],
            ],
        ]);
        $res->assertRedirect();

        $dn = DeliveryNote::with('lines')->firstOrFail();
        $this->assertMatchesRegularExpression('#^\d+/GS-DO/\d{2}/\d{4}$#', $dn->number);
        $this->assertSame('Site A, Jakarta', $dn->delivery_address);
        $this->assertSame('ekspedisi', $dn->delivery_method);
        $this->assertSame('JNE123456', $dn->tracking_number);
        $this->assertTrue($dn->hasDispatchProof());
        $this->assertSame(2, $dn->lines->count());

        $routerLine = $dn->lines->firstWhere('item_name', 'Router');
        $this->assertSame('2.00', $routerLine->qty_ordered);
        $this->assertSame('2.00', $routerLine->qty_previous_balance); // belum ada DN sebelumnya
        $this->assertSame('2.00', $routerLine->qty_delivered);
        $this->assertSame('0.00', $routerLine->qty_balance);

        $switchLine = $dn->lines->firstWhere('item_name', 'Switch');
        $this->assertSame('4.00', $switchLine->qty_ordered);
        $this->assertSame('4.00', $switchLine->qty_previous_balance);
        $this->assertSame('1.00', $switchLine->qty_delivered);
        $this->assertSame('3.00', $switchLine->qty_balance);
    }

    public function test_second_delivery_note_carries_previous_balance(): void
    {
        Storage::fake('local');
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $so = $this->mixedSalesOrder();
        $switch = $so->lines->firstWhere('item_name', 'Switch');

        $this->actingAs($ops)->post("/operational/sales-orders/{$so->id}/delivery-notes", [
            'delivery_method' => 'sendiri',
            'delivery_address' => 'Site A',
            'dispatch_proof' => UploadedFile::fake()->image('bukti1.jpg'),
            'lines' => [['sales_order_line_id' => $switch->id, 'qty_delivered' => 1]],
        ]);

        $this->actingAs($ops)->post("/operational/sales-orders/{$so->id}/delivery-notes", [
            'delivery_method' => 'sendiri',
            'delivery_address' => 'Site A',
            'dispatch_proof' => UploadedFile::fake()->image('bukti2.jpg'),
            'lines' => [['sales_order_line_id' => $switch->id, 'qty_delivered' => 3]],
        ])->assertRedirect();

        $secondDn = DeliveryNote::with('lines')->latest('id')->first();
        $line = $secondDn->lines->firstWhere('item_name', 'Switch');
        $this->assertSame('3.00', $line->qty_previous_balance); // 4 - 1 sudah terkirim di DN pertama
        $this->assertSame('3.00', $line->qty_delivered);
        $this->assertSame('0.00', $line->qty_balance);
    }

    public function test_cannot_deliver_more_than_remaining_balance(): void
    {
        Storage::fake('local');
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $so = $this->mixedSalesOrder();
        $router = $so->lines->firstWhere('item_name', 'Router');

        $this->actingAs($ops)->post("/operational/sales-orders/{$so->id}/delivery-notes", [
            'delivery_method' => 'sendiri',
            'delivery_address' => 'Site A',
            'dispatch_proof' => UploadedFile::fake()->image('bukti.jpg'),
            'lines' => [['sales_order_line_id' => $router->id, 'qty_delivered' => 5]],
        ])->assertSessionHasErrors('lines');

        $this->assertSame(0, DeliveryNote::count());
    }

    public function test_service_lines_cannot_be_delivered(): void
    {
        Storage::fake('local');
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $so = $this->mixedSalesOrder();
        $jasa = $so->lines->firstWhere('item_name', 'Jasa Instalasi');

        $this->actingAs($ops)->post("/operational/sales-orders/{$so->id}/delivery-notes", [
            'delivery_method' => 'sendiri',
            'delivery_address' => 'Site A',
            'dispatch_proof' => UploadedFile::fake()->image('bukti.jpg'),
            'lines' => [['sales_order_line_id' => $jasa->id, 'qty_delivered' => 1]],
        ])->assertSessionHasErrors('lines');
    }

    public function test_operational_can_upload_optional_received_proof_anytime(): void
    {
        Storage::fake('local');
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $so = $this->mixedSalesOrder();
        $router = $so->lines->firstWhere('item_name', 'Router');

        $this->actingAs($ops)->post("/operational/sales-orders/{$so->id}/delivery-notes", [
            'delivery_method' => 'sendiri',
            'delivery_address' => 'Site A',
            'dispatch_proof' => UploadedFile::fake()->image('bukti.jpg'),
            'lines' => [['sales_order_line_id' => $router->id, 'qty_delivered' => 2]],
        ]);
        $dn = DeliveryNote::firstOrFail();
        $this->assertFalse($dn->hasReceivedProof());

        $this->actingAs($ops)->post("/operational/delivery-notes/{$dn->id}/received-proof", [
            'proof' => UploadedFile::fake()->image('diterima.jpg'),
        ])->assertRedirect();

        $this->assertTrue($dn->hasReceivedProof());
        // Status "sent" tidak berubah — bukti diterima customer sifatnya dokumentasi tambahan saja.
        $this->assertSame('sent', $dn->fresh()->status);
    }

    public function test_non_operational_cannot_upload_received_proof(): void
    {
        Storage::fake('local');
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $so = $this->mixedSalesOrder();
        $router = $so->lines->firstWhere('item_name', 'Router');

        $this->actingAs($ops)->post("/operational/sales-orders/{$so->id}/delivery-notes", [
            'delivery_method' => 'sendiri',
            'delivery_address' => 'Site A',
            'dispatch_proof' => UploadedFile::fake()->image('bukti.jpg'),
            'lines' => [['sales_order_line_id' => $router->id, 'qty_delivered' => 2]],
        ]);
        $dn = DeliveryNote::firstOrFail();

        $sales = User::factory()->create(['role' => 'sales']);
        $this->actingAs($sales)->post("/operational/delivery-notes/{$dn->id}/received-proof", [
            'proof' => UploadedFile::fake()->image('diterima.jpg'),
        ])->assertForbidden();
    }

    public function test_non_operational_cannot_create_delivery_note(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $so = $this->mixedSalesOrder();

        $this->actingAs($sales)->get("/operational/sales-orders/{$so->id}/delivery-notes/create")->assertForbidden();
    }
}
