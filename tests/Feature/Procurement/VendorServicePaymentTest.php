<?php

namespace Tests\Feature\Procurement;

use App\Actions\Operational\InitializeProject;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorServicePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsProcurementProject;
use Tests\TestCase;

class VendorServicePaymentTest extends TestCase
{
    use BuildsProcurementProject;
    use RefreshDatabase;

    private function procurement(): User
    {
        return User::factory()->create(['role' => 'procurement', 'is_active' => true]);
    }

    private function vendor(bool $technical = true): Vendor
    {
        return Vendor::create(['name' => 'Vendor Jasa', 'provides_technical' => $technical]);
    }

    /** @return array<string, mixed> */
    private function dealPayload(Vendor $vendor, array $override = []): array
    {
        return array_merge([
            'vendor_id' => $vendor->id,
            'total_fee' => 10000000,
            'terms' => 'dp_final',
            'dp_amount' => 4000000,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'PT Vendor Jasa',
        ], $override);
    }

    public function test_procurement_saves_dp_deal_and_finance_gets_dp_task(): void
    {
        $project = $this->materialProject();
        $procurement = $this->procurement();
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $operational = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $vendor = $this->vendor();

        $this->actingAs($procurement)
            ->put("/procurement/project-procurements/{$project->id}/vendor-service", $this->dealPayload($vendor))
            ->assertRedirect();

        $payment = VendorServicePayment::firstOrFail();
        $this->assertSame('awaiting_dp', $payment->status->value);
        $this->assertNull($payment->released_at);
        $this->assertSame(6000000.0, $payment->finalAmount());
        $this->assertSame(40.0, $payment->dpPercent());
        $this->assertMatchesRegularExpression('#^1/GS-VP/\d{2}/\d{4}$#', $payment->number);
        $this->assertSame($vendor->id, $project->fresh()->vendor_id);

        $this->assertDatabaseHas('notifications', ['user_id' => $finance->id, 'type' => 'vendor_service.dp_due', 'related_id' => $project->id]);
        // Operasional belum dilepas sebelum DP dibayar.
        $this->assertDatabaseMissing('notifications', ['user_id' => $operational->id, 'type' => 'vendor_service.released']);
    }

    public function test_pay_at_end_releases_to_operational_and_informs_finance(): void
    {
        $project = $this->materialProject();
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $operational = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $vendor = $this->vendor();

        $this->actingAs($this->procurement())
            ->put("/procurement/project-procurements/{$project->id}/vendor-service", $this->dealPayload($vendor, ['terms' => 'pay_at_end', 'dp_amount' => 999]))
            ->assertRedirect();

        $payment = VendorServicePayment::firstOrFail();
        $this->assertSame('in_progress', $payment->status->value);
        $this->assertNotNull($payment->released_at);
        $this->assertNull($payment->dp_amount);
        $this->assertSame(10000000.0, $payment->finalAmount());

        $this->assertDatabaseHas('notifications', ['user_id' => $operational->id, 'type' => 'vendor_service.released', 'related_id' => $project->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $finance->id, 'type' => 'vendor_service.pay_after_bast', 'related_id' => $project->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $finance->id, 'type' => 'vendor_service.dp_due']);
    }

    public function test_deal_validation(): void
    {
        $project = $this->materialProject();
        $procurement = $this->procurement();
        $vendor = $this->vendor();
        $url = "/procurement/project-procurements/{$project->id}/vendor-service";

        $this->actingAs($procurement)->put($url, $this->dealPayload($vendor, ['dp_amount' => null]))->assertSessionHasErrors('dp_amount');
        $this->actingAs($procurement)->put($url, $this->dealPayload($vendor, ['dp_amount' => 10000000]))->assertSessionHasErrors('dp_amount');
        $this->actingAs($procurement)->put($url, $this->dealPayload($vendor, ['bank_name' => '']))->assertSessionHasErrors('bank_name');
        $this->actingAs($procurement)->put($url, $this->dealPayload($vendor, ['account_number' => '']))->assertSessionHasErrors('account_number');
        $this->actingAs($procurement)->put($url, $this->dealPayload($this->vendor(false)))->assertSessionHasErrors('vendor_id');
        $this->actingAs($procurement)->put($url, $this->dealPayload($vendor, ['total_fee' => 0]))->assertSessionHasErrors('total_fee');

        $this->assertSame(0, VendorServicePayment::count());
    }

    public function test_only_procurement_can_save_deal(): void
    {
        $project = $this->materialProject();
        $vendor = $this->vendor();
        $url = "/procurement/project-procurements/{$project->id}/vendor-service";

        foreach (['operational', 'finance', 'sales', 'management'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]))
                ->put($url, $this->dealPayload($vendor))->assertForbidden();
        }

        $this->assertSame(0, VendorServicePayment::count());
    }

    public function test_editing_terms_switches_status_and_clears_stale_finance_task(): void
    {
        $project = $this->materialProject();
        $procurement = $this->procurement();
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $vendor = $this->vendor();
        $url = "/procurement/project-procurements/{$project->id}/vendor-service";

        $this->actingAs($procurement)->put($url, $this->dealPayload($vendor));
        $this->actingAs($procurement)->put($url, $this->dealPayload($vendor, ['terms' => 'pay_at_end']));

        $this->assertSame(1, VendorServicePayment::count());
        $this->assertSame('in_progress', VendorServicePayment::firstOrFail()->status->value);
        $this->assertNotNull(Notification::where('user_id', $finance->id)->where('type', 'vendor_service.dp_due')->first()->read_at);
    }

    public function test_paid_deal_cannot_be_edited(): void
    {
        $project = $this->materialProject();
        $vendor = $this->vendor();
        $url = "/procurement/project-procurements/{$project->id}/vendor-service";
        $this->actingAs($this->procurement())->put($url, $this->dealPayload($vendor));
        VendorServicePayment::firstOrFail()->update(['status' => 'paid']);

        $this->actingAs($this->procurement())->put($url, $this->dealPayload($vendor, ['total_fee' => 1]))->assertForbidden();
    }

    public function test_procurement_page_shows_deal_and_lists_service_only_flagged_project(): void
    {
        $project = $this->materialProject();
        $project->actualProcurements()->delete();
        $project->update(['needs_outside_vendor' => true]);
        $procurement = $this->procurement();

        $this->actingAs($procurement)->get('/procurement/project-procurements')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('projects', 1)->where('projects.0.needs_outside_vendor', true));

        $this->actingAs($procurement)->get("/procurement/project-procurements/{$project->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('vendorService', null)->where('canManageVendorService', true)->where('project.needs_outside_vendor', true)->has('serviceCostEstimate'));

        $this->actingAs($procurement)->put("/procurement/project-procurements/{$project->id}/vendor-service", $this->dealPayload($this->vendor()));

        $this->actingAs($procurement)->get("/procurement/project-procurements/{$project->id}")
            ->assertInertia(fn ($page) => $page->where('vendorService.status', 'awaiting_dp')->where('vendorService.dp_percent', 40));
    }

    public function test_sales_can_flag_lead_and_flag_is_copied_to_project(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);

        $this->actingAs($sales)->post('/sales/leads', [
            'contact_id' => $contact->id, 'stage' => 'new', 'needs_outside_vendor' => true,
        ])->assertRedirect();
        $this->assertTrue(Lead::firstOrFail()->needs_outside_vendor);

        $project = $this->materialProject();
        $order = $project->salesOrder;
        $order->quotation->lead->update(['needs_outside_vendor' => true]);
        $procurementUser = $this->procurement();
        $project->delete();

        $new = app(InitializeProject::class)->handle($order->fresh());

        $this->assertTrue(Project::findOrFail($new->id)->needs_outside_vendor);
        $this->assertDatabaseHas('notifications', ['user_id' => $procurementUser->id, 'type' => 'vendor_service.needed', 'related_id' => $new->id]);
    }
}
