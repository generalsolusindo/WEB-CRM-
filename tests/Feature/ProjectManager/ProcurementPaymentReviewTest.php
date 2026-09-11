<?php

namespace Tests\Feature\ProjectManager;

use App\Actions\Procurement\SubmitProcurementPayment;
use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsProcurementProject;
use Tests\TestCase;

class ProcurementPaymentReviewTest extends TestCase
{
    use BuildsProcurementProject;
    use RefreshDatabase;

    private function submitted(Project $project): \App\Models\ProcurementPayment
    {
        $vendor = Vendor::create(['name' => 'PT V']);
        $project->actualProcurements()->update(['vendor_id' => $vendor->id, 'cost_price' => 700000]);
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);

        return app(SubmitProcurementPayment::class)->handle($project->fresh(), $procurement, ['pricing_mode' => 'itemized']);
    }

    public function test_only_delegated_pm_sees_and_opens_the_request(): void
    {
        $project = $this->materialProject();
        $payment = $this->submitted($project);
        $pm = User::find($project->delegated_to);
        $otherPm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);

        $this->actingAs($pm)->get('/project-manager/procurement-payments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ProcurementPayments/Review/Index')->has('payments', 1));

        $this->actingAs($otherPm)->get("/project-manager/procurement-payments/{$payment->id}")->assertForbidden();

        $this->actingAs($pm)->get("/project-manager/procurement-payments/{$payment->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ProcurementPayments/Review/Show')
                ->where('canReview', true)
                ->where('payment.totals.estimated', fn ($v) => (float) $v > 0));
    }

    public function test_pm_approves_forwards_to_finance(): void
    {
        $project = $this->materialProject();
        $payment = $this->submitted($project);
        $pm = User::find($project->delegated_to);
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);

        $this->actingAs($pm)->post("/project-manager/procurement-payments/{$payment->id}/review", ['approved' => true])
            ->assertRedirect('/project-manager/procurement-payments');

        $this->assertSame('approved_pm', $payment->fresh()->status->value);
        $this->assertDatabaseHas('notifications', ['user_id' => $finance->id, 'type' => 'procurement_payment.approved_pm']);
    }

    public function test_pm_reject_requires_notes_and_returns_to_procurement(): void
    {
        $project = $this->materialProject();
        $payment = $this->submitted($project);
        $pm = User::find($project->delegated_to);

        $this->actingAs($pm)->post("/project-manager/procurement-payments/{$payment->id}/review", ['approved' => false])
            ->assertSessionHasErrors('notes');

        $this->actingAs($pm)->post("/project-manager/procurement-payments/{$payment->id}/review", [
            'approved' => false, 'notes' => 'Harga switch kemahalan, cari vendor lain.',
        ])->assertRedirect();

        $this->assertSame('rejected_pm', $payment->fresh()->status->value);
        $this->assertSame('Harga switch kemahalan, cari vendor lain.', $payment->fresh()->pm_notes);
    }

    public function test_finance_cannot_review(): void
    {
        $project = $this->materialProject();
        $payment = $this->submitted($project);
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);

        $this->actingAs($finance)->post("/project-manager/procurement-payments/{$payment->id}/review", ['approved' => true])
            ->assertForbidden();
    }
}
