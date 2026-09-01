<?php

namespace App\Observers;

use App\Models\Attachment;
use App\Models\Payment;
use App\Services\Sales\SalesOrderWinNotifier;

class AttachmentObserver
{
    public function __construct(private SalesOrderWinNotifier $winNotifier) {}

    public function created(Attachment $attachment): void
    {
        if ($attachment->category !== 'payment_proof') {
            return;
        }

        $owner = $attachment->attachable;

        if ($owner instanceof Payment) {
            $this->winNotifier->fromPayment($owner);
        }
    }
}
