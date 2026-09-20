<?php

namespace Tests\Feature\Finance;

use App\Models\Bast;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Sow;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorServicePayment;
use App\Services\Dashboard\MenuBadges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsProcurementProject;
use Tests\TestCase;

class VendorServicePaymentFlowTest extends TestCase
{
    use BuildsProcurementProject;
    use RefreshDatabase;

    private User $finance;

    private User $procurement;

    private User $ops;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $this->procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $this->ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
    }

    /** Project + deal dari Procurement. */
    private function deal(string $terms = 'dp_final'): VendorServicePayment
    {
        $project = $this->materialProject();
        $vendor = Vendor::create(['name' => 'Vendor Jasa', 'provides_technical' => true]);

        $this->actingAs($this->procurement)->put("/procurement/project-procurements/{$project->id}/vendor-service", [
            'vendor_id' => $vendor->id, 'total_fee' => 10000000, 'terms' => $terms, 'dp_amount' => $terms === 'dp_final' ? 4000000 : null,
            'bank_name' => 'BCA', 'account_number' => '123', 'account_holder' => 'PT Vendor',
        ]);

        return VendorServicePayment::firstOrFail();
    }

    private function pay(VendorServicePayment $deal, string $kind, array $override = [])
    {
        return $this->actingAs($this->finance)->post("/finance/vendor-service-payments/{$deal->id}/pay", array_merge([
            'kind' => $kind,
            'paid_at' => now()->subMinute()->toDateTimeString(),
            'proof' => UploadedFile::fake()->create('tf.pdf', 20, 'application/pdf'),
        ], $override));
    }

    private function verifyBast(VendorServicePayment $deal): void
    {
        Bast::create(['project_id' => $deal->project_id, 'status' => 'verified', 'submitted_at' => now(), 'verified_at' => now()]);
    }

    public function test_finance_pays_dp_with_locked_amount_and_operational_is_released(): void
    {
        $deal = $this->deal();

        // Nominal dari client diabaikan — selalu mengikuti deal Procurement.
        $this->pay($deal, 'dp', ['amount' => 1])->assertRedirect()->assertSessionHasNoErrors();

        $deal->refresh();
        $entry = $deal->entries()->firstOrFail();
        $this->assertSame('4000000.00', $entry->amount);
        $this->assertSame('in_progress', $deal->status->value);
        $this->assertNotNull($deal->released_at);
        $this->assertDatabaseHas('attachments', ['attachable_id' => $entry->id, 'category' => 'payment_proof']);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->ops->id, 'type' => 'vendor_service.released']);

        // Sudah ada transfer -> Procurement tidak bisa mengubah deal lagi.
        $this->actingAs($this->procurement)->put("/procurement/project-procurements/{$deal->project_id}/vendor-service", [
            'vendor_id' => $deal->vendor_id, 'total_fee' => 1, 'terms' => 'pay_at_end',
            'bank_name' => 'x', 'account_number' => '1', 'account_holder' => 'x',
        ])->assertForbidden();
    }

    public function test_payment_requires_proof_and_finance_role_and_cannot_repeat(): void
    {
        $deal = $this->deal();

        $this->pay($deal, 'dp', ['proof' => null])->assertSessionHasErrors('proof');
        $this->actingAs($this->ops)->post("/finance/vendor-service-payments/{$deal->id}/pay", [
            'kind' => 'dp', 'paid_at' => now()->subMinute()->toDateTimeString(),
            'proof' => UploadedFile::fake()->create('tf.pdf', 20, 'application/pdf'),
        ])->assertForbidden();

        $this->pay($deal, 'dp')->assertSessionHasNoErrors();
        $this->pay($deal, 'dp')->assertSessionHasErrors('kind');
        $this->assertSame(1, $deal->entries()->count());
    }

    public function test_dp_cannot_be_paid_for_pay_at_end_terms(): void
    {
        $deal = $this->deal('pay_at_end');

        $this->pay($deal, 'dp')->assertSessionHasErrors('kind');
        $this->assertSame(0, $deal->entries()->count());
    }

    public function test_final_payment_is_blocked_until_bast_is_verified(): void
    {
        $deal = $this->deal();
        $this->pay($deal, 'dp');

        $this->pay($deal, 'final')->assertSessionHasErrors('kind');
        $this->assertSame('in_progress', $deal->fresh()->status->value);

        $this->verifyBast($deal);
        $this->pay($deal, 'final')->assertSessionHasNoErrors();

        $deal->refresh();
        $this->assertSame('paid', $deal->status->value);
        $this->assertSame('6000000.00', $deal->entries()->where('kind', 'final')->firstOrFail()->amount);
    }

    public function test_pay_at_end_final_is_full_fee_and_needs_bast(): void
    {
        $deal = $this->deal('pay_at_end');

        $this->pay($deal, 'final')->assertSessionHasErrors('kind');
        $this->verifyBast($deal);
        $this->pay($deal, 'final')->assertSessionHasNoErrors();

        $this->assertSame('10000000.00', $deal->entries()->firstOrFail()->amount);
        $this->assertSame('paid', $deal->fresh()->status->value);
    }

    public function test_cancel_dp_relocks_operational_and_keeps_audit_trail(): void
    {
        $deal = $this->deal();
        $this->pay($deal, 'dp');
        $entry = $deal->entries()->firstOrFail();

        $this->actingAs($this->finance)->post("/finance/vendor-service-payments/{$deal->id}/entries/{$entry->id}/cancel", [])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->finance)->post("/finance/vendor-service-payments/{$deal->id}/entries/{$entry->id}/cancel", ['reason' => 'Salah input, harusnya DP saja'])
            ->assertSessionHasNoErrors();

        $deal->refresh();
        $this->assertSame('awaiting_dp', $deal->status->value);
        $this->assertNull($deal->released_at);
        $this->assertSame(0, $deal->entries()->count());
        $trashed = $deal->entries()->onlyTrashed()->firstOrFail();
        $this->assertSame('Salah input, harusnya DP saja', $trashed->cancellation_reason);
        $this->assertSame($this->finance->id, $trashed->cancelled_by);
        $this->assertSame(1, $trashed->attachments()->count());

        // Setelah dibatalkan, DP bisa dibayar ulang dan deal bisa diedit lagi.
        $this->pay($deal, 'dp')->assertSessionHasNoErrors();
    }

    public function test_cancel_final_returns_to_in_progress_and_dp_cancel_is_guarded(): void
    {
        $deal = $this->deal();
        $this->pay($deal, 'dp');
        $this->verifyBast($deal);
        $this->pay($deal, 'final');
        $dp = $deal->entries()->where('kind', 'dp')->firstOrFail();
        $final = $deal->entries()->where('kind', 'final')->firstOrFail();

        // DP tidak boleh dibatalkan selama pelunasan aktif.
        $this->actingAs($this->finance)->post("/finance/vendor-service-payments/{$deal->id}/entries/{$dp->id}/cancel", ['reason' => 'x'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->finance)->post("/finance/vendor-service-payments/{$deal->id}/entries/{$final->id}/cancel", ['reason' => 'Salah nominal'])
            ->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $deal->fresh()->status->value);
    }

    public function test_dp_cancel_blocked_once_project_is_running(): void
    {
        $deal = $this->deal();
        $this->pay($deal, 'dp');
        $deal->project->update(['status' => 'in_progress']);
        $entry = $deal->entries()->firstOrFail();

        $this->actingAs($this->finance)->post("/finance/vendor-service-payments/{$deal->id}/entries/{$entry->id}/cancel", ['reason' => 'x'])
            ->assertSessionHasErrors('reason');
        $this->assertSame('in_progress', $deal->fresh()->status->value);
    }

    public function test_dp_cancel_blocked_once_sow_is_submitted_to_hr(): void
    {
        $deal = $this->deal();
        $this->pay($deal, 'dp');
        $technician = User::factory()->create(['role' => 'technician', 'vendor_id' => $deal->vendor_id, 'is_active' => true]);
        Sow::create([
            'project_id' => $deal->project_id, 'status' => 'pending_hr_review', 'number' => 'SOW-1',
            'project_name' => 'X', 'technician_id' => $technician->id,
        ]);
        $entry = $deal->entries()->firstOrFail();

        $this->actingAs($this->finance)->post("/finance/vendor-service-payments/{$deal->id}/entries/{$entry->id}/cancel", ['reason' => 'x'])
            ->assertSessionHasErrors('reason');
        $this->assertSame('in_progress', $deal->fresh()->status->value);
    }

    public function test_operational_is_gated_until_dp_paid(): void
    {
        $deal = $this->deal();
        $project = $deal->project;
        $project->update(['status' => 'planning']);

        $this->actingAs($this->ops)->get("/operational/projects/{$project->id}/sow")->assertForbidden();
        $this->assertFalse($this->ops->can('markReady', $project->fresh()));

        $this->pay($deal, 'dp');

        $this->actingAs($this->ops)->get("/operational/projects/{$project->id}/sow")->assertOk();
        $this->assertTrue($this->ops->can('viewSow', $project->fresh()));
    }

    public function test_pay_at_end_is_released_immediately_and_flagged_project_without_deal_is_gated(): void
    {
        $deal = $this->deal('pay_at_end');
        $this->actingAs($this->ops)->get("/operational/projects/{$deal->project_id}/sow")->assertOk();

        $flagged = Project::findOrFail($deal->project_id);
        $flagged->vendorServicePayment()->delete();
        $flagged->update(['needs_outside_vendor' => true]);
        $this->assertFalse($this->ops->can('viewSow', $flagged->fresh()));

        // Data lama: vendor sudah terpasang tanpa penanda & tanpa deal -> tidak terkena gerbang.
        $flagged->update(['needs_outside_vendor' => false]);
        $this->assertTrue($this->ops->can('viewSow', $flagged->fresh()));
    }

    private function submitBast(VendorServicePayment $deal): Bast
    {
        $deal->project->update(['status' => 'verification']);

        return Bast::create([
            'project_id' => $deal->project_id, 'status' => 'submitted',
            'submitted_by' => User::factory()->create(['role' => 'technician'])->id, 'submitted_at' => now(),
        ]);
    }

    public function test_verified_bast_turns_pay_after_bast_marker_into_a_final_payment_task(): void
    {
        $deal = $this->deal('pay_at_end');
        $this->assertDatabaseHas('notifications', ['user_id' => $this->finance->id, 'type' => 'vendor_service.pay_after_bast']);
        $bast = $this->submitBast($deal);

        $this->actingAs($this->ops)->put("/operational/projects/{$deal->project_id}/bast/{$bast->id}", ['decision' => 'approve'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('notifications', ['user_id' => $this->finance->id, 'type' => 'vendor_service.final_due', 'related_id' => $deal->project_id]);
        $this->assertNotNull(Notification::where('type', 'vendor_service.pay_after_bast')->first()->read_at);

        $this->pay($deal, 'final')->assertSessionHasNoErrors();
        $this->assertNotNull(Notification::where('type', 'vendor_service.final_due')->first()->read_at);
    }

    public function test_rejected_bast_does_not_release_final_payment(): void
    {
        $deal = $this->deal();
        $this->pay($deal, 'dp');
        $bast = $this->submitBast($deal);

        $this->actingAs($this->ops)->put("/operational/projects/{$deal->project_id}/bast/{$bast->id}", ['decision' => 'reject', 'notes' => 'Belum rapi'])
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('notifications', ['type' => 'vendor_service.final_due']);
        $this->pay($deal, 'final')->assertSessionHasErrors('kind');
    }

    public function test_bast_without_vendor_deal_is_unaffected(): void
    {
        $project = $this->materialProject();
        $project->update(['status' => 'verification']);
        $bast = Bast::create([
            'project_id' => $project->id, 'status' => 'submitted',
            'submitted_by' => User::factory()->create(['role' => 'technician'])->id, 'submitted_at' => now(),
        ]);

        $this->actingAs($this->ops)->put("/operational/projects/{$project->id}/bast/{$bast->id}", ['decision' => 'approve'])
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('notifications', ['type' => 'vendor_service.final_due']);
    }

    public function test_finance_pages_menu_badge_and_access(): void
    {
        $deal = $this->deal();

        $this->actingAs($this->finance)->get('/finance/vendor-service-payments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Finance/VendorServicePayments/Index')->where('payments.0.next', 'Bayar DP'));

        $this->actingAs($this->finance)->get("/finance/vendor-service-payments/{$deal->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canPayDp', true)->where('canPayFinal', false)->where('payment.bank_name', 'BCA'));

        $this->assertSame(1, app(MenuBadges::class)->for($this->finance)['/finance/vendor-service-payments']);
        $this->pay($deal, 'dp');
        $this->assertSame(0, app(MenuBadges::class)->for($this->finance)['/finance/vendor-service-payments']);
        $this->verifyBast($deal);
        $this->assertSame(1, app(MenuBadges::class)->for($this->finance)['/finance/vendor-service-payments']);

        $this->actingAs($this->ops)->get('/finance/vendor-service-payments')->assertForbidden();
    }
}
