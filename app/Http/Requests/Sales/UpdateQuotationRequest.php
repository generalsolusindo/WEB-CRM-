<?php

namespace App\Http\Requests\Sales;

use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('quotation')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string', 'max:4000'],
            'agreed_dpp' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.procurement_request_line_id' => ['required', 'integer', 'distinct'],
            'lines.*.selling_price' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'lines.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.tax_id' => [
                'nullable',
                'integer',
                Rule::exists('taxes', 'id')->where('is_active', true),
            ],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Quotation $quotation */
            $quotation = $this->route('quotation');

            $validPrLineIds = $quotation->lines()
                ->pluck('procurement_request_line_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();

            $submittedIds = collect($this->input('lines', []))
                ->pluck('procurement_request_line_id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            sort($validPrLineIds);

            if ($submittedIds !== $validPrLineIds) {
                $validator->errors()->add('lines', 'Susunan kebutuhan tidak dapat diubah dari editor quotation. Gunakan Revisi Kebutuhan agar Procurement melakukan costing ulang.');
            }
        });
    }
}
