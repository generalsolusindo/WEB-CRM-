<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SaveSalesOrderDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageDocuments', $this->route('salesOrder')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'signed_quotation' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:1024'],
            'purchase_order' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:1024'],
            'po_number' => ['nullable', 'string', 'max:100'],
            'po_date' => ['nullable', 'date'],
        ];
    }
}
