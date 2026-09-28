<?php

namespace Tests\Feature\Sales;

use App\Contracts\ConversionSheetWriter;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class QuotationConversionSheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_can_push_a_draft_quotation_and_row_matches_expected_shape(): void
    {
        $fake = new class implements ConversionSheetWriter
        {
            public array $rows = [];

            public function appendRow(array $row): void
            {
                $this->rows[] = $row;
            }
        };
        $this->app->instance(ConversionSheetWriter::class, $fake);

        $quotation = $this->draftQuotationForContact('CV Kusuma Karya', '0812-3456-7890');
        $this->assertSame('draft', $quotation->status);

        $this->actingAs($this->sales($quotation))
            ->post("/sales/quotations/{$quotation->id}/push-conversion")
            ->assertRedirect();

        $this->assertCount(1, $fake->rows);
        [$phone, $conversionName, $conversionTime, $conversionValue, $currency] = $fake->rows[0];

        $this->assertSame('+6281234567890', $phone);
        $this->assertSame('CV Kusuma Karya', $conversionName);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $conversionTime);
        $this->assertSame(1300000.0, $conversionValue);
        $this->assertSame('IDR', $currency);

        $this->assertNotNull($quotation->fresh()->pushed_to_conversion_sheet_at);
    }

    public function test_perorangan_contact_without_company_falls_back_to_name(): void
    {
        $fake = new class implements ConversionSheetWriter
        {
            public array $rows = [];

            public function appendRow(array $row): void
            {
                $this->rows[] = $row;
            }
        };
        $this->app->instance(ConversionSheetWriter::class, $fake);

        $quotation = $this->draftQuotationForContact(null, '0812-3456-7890', 'Alex');

        $this->actingAs($this->sales($quotation))
            ->post("/sales/quotations/{$quotation->id}/push-conversion");

        $this->assertSame('Alex (perorangan)', $fake->rows[0][1]);
    }

    public function test_cannot_push_without_a_phone_number(): void
    {
        $this->app->instance(ConversionSheetWriter::class, new class implements ConversionSheetWriter
        {
            public function appendRow(array $row): void
            {
                throw new RuntimeException('Tidak boleh terpanggil.');
            }
        });

        $quotation = $this->draftQuotationForContact('PT Tanpa Telepon', '');

        $this->actingAs($this->sales($quotation))
            ->post("/sales/quotations/{$quotation->id}/push-conversion")
            ->assertSessionHas('error');

        $this->assertNull($quotation->fresh()->pushed_to_conversion_sheet_at);
    }

    public function test_google_failure_is_shown_as_error_and_not_marked_as_sent(): void
    {
        $this->app->instance(ConversionSheetWriter::class, new class implements ConversionSheetWriter
        {
            public function appendRow(array $row): void
            {
                throw new RuntimeException('izin sheet ditolak');
            }
        });

        $quotation = $this->draftQuotationForContact('CV Kusuma Karya', '0812-3456-7890');

        $this->actingAs($this->sales($quotation))
            ->post("/sales/quotations/{$quotation->id}/push-conversion")
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'izin sheet ditolak'));

        $this->assertNull($quotation->fresh()->pushed_to_conversion_sheet_at);
    }

    public function test_other_sales_cannot_push_someone_elses_quotation(): void
    {
        $quotation = $this->draftQuotationForContact('CV Kusuma Karya', '0812-3456-7890');
        $otherSales = User::factory()->create(['role' => 'sales']);

        $this->actingAs($otherSales)
            ->post("/sales/quotations/{$quotation->id}/push-conversion")
            ->assertForbidden();
    }

    private function draftQuotationForContact(?string $companyName, string $phone, string $contactName = 'Budi'): Quotation
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create([
            'name' => $contactName, 'company_name' => $companyName, 'phone' => $phone, 'created_by' => $sales->id,
        ]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'qty' => 1, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines()->first()->id, 'selling_price' => 1300000]],
        ]);

        return Quotation::firstOrFail();
    }

    private function sales(Quotation $quotation): User
    {
        return User::find($quotation->sales_id);
    }
}
