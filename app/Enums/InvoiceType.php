<?php

namespace App\Enums;

enum InvoiceType: string
{
    case Sale = 'sale';
    case Survey = 'survey';
}
