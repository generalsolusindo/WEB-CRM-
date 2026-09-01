<?php

namespace App\Actions\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\SurveyStatus;
use App\Models\Invoice;
use App\Models\Survey;
use App\Models\Tax;
use App\Models\User;
use App\Services\DocumentNumber;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSurveyInvoice
{
    public function __construct(
        private DocumentNumber $documentNumber,
        private Notify $notify,
    ) {}

    public function handle(Survey $survey, User $user, ?string $dueDate = null, ?int $taxId = null, ?string $note = null): Invoice
    {
        return DB::transaction(function () use ($survey, $user, $dueDate, $taxId, $note) {
            $locked = Survey::query()
                ->with('lead.contact', 'lead.sales')
                ->whereKey($survey->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== SurveyStatus::FinanceReview->value) {
                throw ValidationException::withMessages([
                    'survey' => 'Invoice survey hanya bisa dibuat saat survey menunggu Finance.',
                ]);
            }

            if (! $locked->billable) {
                throw ValidationException::withMessages([
                    'survey' => 'Survey ini tidak ditagihkan ke customer.',
                ]);
            }

            if ($locked->invoices()->where('status', '!=', 'cancelled')->exists()) {
                throw ValidationException::withMessages([
                    'survey' => 'Invoice survey aktif sudah ada.',
                ]);
            }

            if ((float) $locked->cost <= 0) {
                throw ValidationException::withMessages([
                    'survey' => 'Biaya survey belum ditetapkan Procurement.',
                ]);
            }

            $tax = $taxId ? Tax::where('is_active', true)->find($taxId) : null;
            $taxRate = $tax ? (float) $tax->rate : 0.0;
            $taxAmount = round((float) $locked->cost * $taxRate / 100, 2);

            $invoice = Invoice::create([
                'number' => $this->documentNumber->nextSurveyInvoiceNumber(),
                'invoice_type' => InvoiceType::Survey->value,
                'sales_order_id' => null,
                'survey_id' => $locked->id,
                'invoice_phase' => null,
                'status' => InvoiceStatus::Sent->value,
                'amount' => $locked->cost,
                'tax_amount' => $taxAmount,
                'due_date' => $dueDate,
                'created_by' => $user->id,
            ]);

            $invoice->lines()->create([
                'sales_order_line_id' => null,
                'item_name' => "Biaya Survey {$locked->code} — {$locked->site_region}",
                'qty' => 1,
                'unit_price' => $locked->cost,
                'discount_amount' => 0,
                'tax_id' => $tax?->id,
                'tax_rate' => $taxRate,
                'subtotal' => $locked->cost,
            ]);

            $locked->update([
                'status' => SurveyStatus::AwaitingPayment->value,
                'finance_handled_by' => $user->id,
                'finance_handled_at' => now(),
                'finance_note' => $note ?: $locked->finance_note,
            ]);

            if ($locked->lead->sales) {
                $this->notify->once(
                    $locked->lead->sales,
                    'survey.invoiced',
                    "Invoice survey {$invoice->number} untuk {$locked->code} sudah diterbitkan. Menunggu pembayaran customer.",
                    $locked,
                );
            }

            return $invoice;
        });
    }
}
