<?php

namespace Tests\Feature\Sales;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsProcurementProject;
use Tests\TestCase;

/**
 * Regresi produksi: Sales tidak punya cara sama sekali menghapus lead miliknya sendiri
 * begitu sudah ada transaksi nyata (Invoice/Project) — tombol "Hapus" biasa hilang total
 * (LeadPolicy::delete() mengunci), dan "Hapus Total" dulu cuma dibuka untuk Manajemen.
 */
class ForceDeleteLeadTest extends TestCase
{
    use BuildsProcurementProject;
    use RefreshDatabase;

    private function leadWithRealTransaction(): Lead
    {
        $project = $this->materialProject();

        return $project->salesOrder->quotation->lead;
    }

    private function owner(Lead $lead): User
    {
        return User::find($lead->sales_id);
    }

    public function test_sales_force_deletes_own_lead_even_with_invoice_and_project(): void
    {
        $lead = $this->leadWithRealTransaction();
        $sales = $this->owner($lead);
        $quotationId = $lead->quotations()->value('id');
        $salesOrderId = $quotationId ? SalesOrder::where('quotation_id', $quotationId)->value('id') : null;

        // Tombol biasa memang sudah tidak boleh dipakai di titik ini.
        $this->assertFalse($sales->can('delete', $lead));
        $this->assertTrue($sales->can('forceDelete', $lead));
        $this->assertNotNull($salesOrderId, 'fixture-nya harus benar-benar punya Sales Order');

        $this->actingAs($sales)->delete("/sales/leads/{$lead->id}/force")
            ->assertRedirect('/sales/leads')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
        $this->assertDatabaseMissing('sales_orders', ['id' => $salesOrderId]);
    }

    public function test_sales_cannot_force_delete_another_sales_persons_lead(): void
    {
        $lead = $this->leadWithRealTransaction();
        $otherSales = User::factory()->create(['role' => 'sales']);

        $this->actingAs($otherSales)->delete("/sales/leads/{$lead->id}/force")->assertForbidden();
        $this->assertDatabaseHas('leads', ['id' => $lead->id]);
    }

    public function test_non_sales_roles_cannot_use_the_sales_force_delete_route(): void
    {
        $lead = $this->leadWithRealTransaction();

        foreach (['management', 'operational', 'finance', 'procurement'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]))
                ->delete("/sales/leads/{$lead->id}/force")
                ->assertForbidden();
        }

        $this->assertDatabaseHas('leads', ['id' => $lead->id]);
    }

    public function test_sales_still_sees_the_lightweight_delete_button_for_a_clean_draft_lead(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);

        $this->actingAs($sales)->get("/sales/leads/{$lead->id}")
            ->assertInertia(fn ($page) => $page
                ->where('canDelete', true)
                ->where('canForceDelete', true));

        // Keduanya boleh true di sini (belum ada transaksi apa pun) — halaman sengaja
        // cuma menampilkan tombol "Hapus" biasa selama canDelete masih true, lihat Show.jsx.
        $this->actingAs($sales)->delete("/sales/leads/{$lead->id}")->assertRedirect('/sales/leads');
        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
    }

    public function test_management_force_delete_is_unaffected_by_the_sales_change(): void
    {
        $lead = $this->leadWithRealTransaction();
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);

        $this->actingAs($management)->delete("/management/opportunities/{$lead->id}")
            ->assertRedirect('/management/opportunities')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
    }
}
