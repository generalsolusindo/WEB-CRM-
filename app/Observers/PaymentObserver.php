<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\Sales\SalesOrderWinNotifier;

class PaymentObserver
{
    public function __construct(private SalesOrderWinNotifier $winNotifier) {}

    public function created(Payment $payment): void
    {
        $this->winNotifier->fromPayment($payment);
    }

    public function updated(Payment $payment): void
    {
        $this->winNotifier->fromPayment($payment);
    }
}
