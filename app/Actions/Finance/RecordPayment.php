<?php

namespace App\Actions\Finance;

use App\Enums\InvoicePhase;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Actions\Operational\InitializeProject;
use App\Models\User;
use App\Services\Notifications\Notify;
use App\Services\Sales\SalesOrderWinNotifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordPayment
{
    public function __construct(
        private Notify $notify,
        private SalesOrderWinNotifier $winNotifier,
        private InitializeProject $initializeProject,
        private AdvanceSurveyToOperational $advanceSurvey,
    ) {}

    public function handle(
        Invoice $invoice,
        User $user,
        float $amountPaid,
        string $paidAt,
        ?string $notes,
        ?UploadedFile $proof,
    ): Payment {
        return DB::transaction(function () use ($invoice, $user, $amountPaid, $paidAt, $notes, $proof) {
            $locked = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === InvoiceStatus::Cancelled->value) {
                throw ValidationException::withMessages([
                    'amount_paid' => 'Invoice sudah dibatalkan.',
                ]);
            }

            $payment = $locked->payments()->create([
                'amount_paid' => $amountPaid,
                'paid_at' => $paidAt,
                'recorded_by' => $user->id,
                'notes' => $notes,
            ]);

            // Update status DULU supaya cek turunan (Won / gate project) melihat status terbaru.
            $grand = (float) $locked->amount + (float) $locked->tax_amount;
            $totalPaid = (float) $locked->payments()->sum('amount_paid');

            $status = match (true) {
                $totalPaid >= $grand => InvoiceStatus::Paid->value,
                $totalPaid > 0 => InvoiceStatus::PartiallyPaid->value,
                default => $locked->status,
            };

            $becamePaid = $status !== $locked->status && $status === InvoiceStatus::Paid->value;

            if ($status !== $locked->status) {
                $locked->update(['status' => $status]);
            }

            if ($proof) {
                $path = $proof->store('payment-proofs');

                $payment->attachments()->create([
                    'category' => 'payment_proof',
                    'file_path' => $path,
                    'uploaded_by' => $user->id,
                ]);
            }

            if ($becamePaid && $locked->isSurvey()) {
                $locked->loadMissing('survey');
                if ($locked->survey) {
                    $this->advanceSurvey->handle($locked->survey);
                }
            }

            if ($becamePaid && in_array($locked->invoice_phase, [InvoicePhase::Dp->value, InvoicePhase::Full->value], true)) {
                $project = $this->initializeProject->handle($locked->salesOrder);
                $this->notifyOperational($locked, $project);
            }

            // Cek eksplisit "siap Won" — idempoten, tak bergantung urutan observer.
            $this->winNotifier->evaluate($locked->salesOrder);

            return $payment;
        });
    }

    private function notifyOperational(Invoice $invoice, \App\Models\Project $project): void
    {
        $invoice->loadMissing('salesOrder.contact');
        $salesOrder = $invoice->salesOrder;
        $customer = $salesOrder->contact?->name ?? 'customer';

        $this->notify->onceForEach(
            User::query()->where('role', 'operational')->where('is_active', true)->get(),
            'invoice.upfront_paid',
            "Invoice muka Sales Order {$salesOrder->number} ({$customer}) sudah lunas. Project sudah dibuat, silakan mulai planning.",
            $project,
        );
    }
}

