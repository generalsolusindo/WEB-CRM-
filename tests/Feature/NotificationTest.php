<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Notifications\Notify;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_once_is_idempotent_per_user_type_and_related(): void
    {
        $user = User::factory()->create(['role' => 'sales']);
        $related = Contact::create(['name' => 'Ref', 'created_by' => $user->id]);
        $notify = app(Notify::class);

        $a = $notify->once($user, 'sales_order.created', 'pesan', $related);
        $b = $notify->once($user, 'sales_order.created', 'pesan lain', $related);

        $this->assertTrue($a->is($b));
        $this->assertSame(1, Notification::count());
    }

    public function test_confirming_sales_order_notifies_all_active_finance_users_once(): void
    {
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $inactiveFinance = User::factory()->create(['role' => 'finance', 'is_active' => false]);

        [$sales, $quotation] = $this->sentQuotation();
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload("material_only"));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $finance->id,
            'type' => 'sales_order.created',
        ]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $inactiveFinance->id]);
        $this->assertSame(1, Notification::where('type', 'sales_order.created')->count());
    }

    public function test_ready_to_win_notification_is_sent_once_when_payment_proof_completes_settlement(): void
    {
        [$sales, $salesOrder] = $this->confirmedSalesOrder();

        $invoice = Invoice::create([
            'sales_order_id' => $salesOrder->id,
            'invoice_phase' => 'final',
            'status' => 'paid',
            'amount' => 2600000,
            'tax_amount' => 0,
        ]);
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'amount_paid' => 2600000,
            'paid_at' => now(),
        ]);

        // Payment recorded, still no proof -> no notification.
        $this->assertSame(0, Notification::where('type', 'sales_order.ready_to_win')->count());

        $payment->attachments()->create(['category' => 'payment_proof', 'file_path' => 'a.pdf']);
        $payment->attachments()->create(['category' => 'payment_proof', 'file_path' => 'b.pdf']);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $sales->id,
            'type' => 'sales_order.ready_to_win',
            'related_id' => $salesOrder->id,
        ]);
        $this->assertSame(1, Notification::where('type', 'sales_order.ready_to_win')->count());
    }

    public function test_notifications_are_shared_to_inertia_and_can_be_marked_read(): void
    {
        $user = User::factory()->create(['role' => 'sales']);
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'sales_order.ready_to_win',
            'message' => 'Cek Sales Order',
            'related_type' => SalesOrder::class,
            'related_id' => 123,
            'is_sent' => true,
        ]);

        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('auth.notifications.unread_count', 1)
            ->where('auth.notifications.items.0.id', $notification->id));

        $this->actingAs($user)->post("/notifications/{$notification->id}/read")
            ->assertRedirect('/sales/sales-orders/123');
        $this->assertNotNull($notification->fresh()->read_at);

        $other = User::factory()->create(['role' => 'sales']);
        $this->actingAs($other)->post("/notifications/{$notification->id}/read")->assertForbidden();
    }

    /** @return array{User, Quotation} */
    private function sentQuotation(): array
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id,
            'sales_id' => $sales->id,
            'type' => 'opportunity',
            'stage' => 'qualified',
        ]);
        $lead->requirements()->create([
            'item_name' => 'Router',
            'qty' => 2,
            'unit' => 'unit',
            'created_by' => $sales->id,
        ]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");

        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [
                ['procurement_request_line_id' => $pr->lines()->first()->id, 'selling_price' => 1300000],
            ],
        ]);
        $quotation = Quotation::firstOrFail();
        $quotation->update(['status' => 'sent']);

        return [$sales, $quotation];
    }

    /** @return array{User, SalesOrder} */
    private function confirmedSalesOrder(): array
    {
        [$sales, $quotation] = $this->sentQuotation();
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload("mixed"));

        return [$sales, SalesOrder::with('quotation.lead')->firstOrFail()];
    }
}
