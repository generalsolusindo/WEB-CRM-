<?php

namespace App\Http\Requests\Finance;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    /** updateMeta = jatuh tempo & catatan selalu boleh; update = rincian baris juga ikut (terkunci kalau sudah ada pembayaran). */
    public function authorize(): bool
    {
        return $this->user()?->can('updateMeta', $this->route('invoice')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $linesEditable = $this->user()?->can('update', $this->route('invoice')) ?? false;

        $rules = [
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if (! $linesEditable) {
            return $rules;
        }

        return [
            ...$rules,
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sales_order_line_id' => ['nullable', 'integer', 'exists:sales_order_lines,id'],
            'lines.*.item_name' => ['required', 'string', 'max:255'],
            'lines.*.category' => ['required', Rule::in(['material', 'service', 'reimburse'])],
            'lines.*.qty' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Invoice $invoice */
            $invoice = $this->route('invoice');

            $validSoLineIds = $invoice->salesOrder?->lines()->pluck('id')->map(fn ($id) => (int) $id)->all() ?? [];

            foreach ($this->input('lines', []) as $i => $line) {
                $soLineId = $line['sales_order_line_id'] ?? null;
                if ($soLineId !== null && ! in_array((int) $soLineId, $validSoLineIds, true)) {
                    $validator->errors()->add("lines.{$i}.sales_order_line_id", 'Baris Sales Order tidak valid untuk invoice ini.');
                }
            }
        });
    }
}
