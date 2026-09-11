<?php

namespace Tests\Feature\Procurement;

use App\Enums\ProcurementPaymentStatus;
use App\Models\ProcurementPayment;
use App\Models\ProcurementPaymentProof;
use App\Models\User;
use App\Services\DocumentNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsProcurementProject;
use Tests\TestCase;

class ProcurementPaymentSchemaTest extends TestCase
{
    use BuildsProcurementProject;
    use RefreshDatabase;

    public function test_material_project_seeds_actual_procurement_rows_with_new_columns(): void
    {
        $project = $this->materialProject([
            ['item_name' => 'Router', 'qty' => 2, 'unit' => 'unit', 'cost_price' => 1000000],
        ]);

        $item = $project->actualProcurements()->firstOrFail();

        $this->assertFalse($item->from_office_stock);
        $this->assertFalse($item->is_paid);
        $this->assertNull($item->paid_at);
        $this->assertNull($item->procurement_payment_id);
    }

    public function test_procurement_payment_number_follows_slash_format(): void
    {
        $number = app(DocumentNumber::class)->nextProcurementPaymentNumber();

        $this->assertMatchesRegularExpression('#^1/GS-PP/\d{2}/\d{4}$#', $number);
    }

    public function test_procurement_payment_relations_and_status_cast(): void
    {
        $project = $this->materialProject();
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);

        $payment = ProcurementPayment::create([
            'project_id' => $project->id,
            'number' => app(DocumentNumber::class)->nextProcurementPaymentNumber(),
            'status' => ProcurementPaymentStatus::Draft->value,
            'pricing_mode' => 'itemized',
        ]);

        $item = $project->actualProcurements()->firstOrFail();
        $item->update(['procurement_payment_id' => $payment->id, 'cost_price' => 500000]);

        $proof = ProcurementPaymentProof::create([
            'procurement_payment_id' => $payment->id,
            'actual_procurement_id' => $item->id,
            'file_path' => 'proofs/x.pdf',
            'uploaded_by' => $procurement->id,
            'uploaded_at' => now(),
        ]);

        $payment->refresh()->load('items', 'proofs');

        $this->assertInstanceOf(ProcurementPaymentStatus::class, $payment->status);
        $this->assertTrue($payment->status->isEditable());
        $this->assertSame(1, $payment->items->count());
        $this->assertSame($item->id, $payment->proofs->first()->actual_procurement_id);
        $this->assertSame($payment->id, $item->fresh()->procurementPayment->id);
        $this->assertEqualsWithDelta(1000000.0, $payment->totalCost(), 0.01); // 500000 * qty 2
        $this->assertSame($proof->id, $item->fresh()->proofs->first()->id);
    }
}
