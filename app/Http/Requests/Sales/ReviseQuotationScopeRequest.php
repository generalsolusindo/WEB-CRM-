<?php

namespace App\Http\Requests\Sales;

use App\Models\Requirement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviseQuotationScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reviseScope', $this->route('quotation')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.procurement_request_line_id' => ['nullable', 'integer', 'distinct'],
            'lines.*.item_name' => ['required', 'string', 'max:255'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit' => ['required', Rule::in(Requirement::UNITS)],
            'lines.*.category' => ['required', Rule::in(['material', 'service', 'reimburse'])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $quotation = $this->route('quotation');
            $validIds = $quotation->procurementRequest->lines()->pluck('id')->map(fn ($id) => (int) $id)->all();

            foreach ($this->input('lines', []) as $index => $line) {
                $lineId = $line['procurement_request_line_id'] ?? null;

                if ($lineId !== null && ! in_array((int) $lineId, $validIds, true)) {
                    $validator->errors()->add("lines.{$index}.procurement_request_line_id", 'Baris kebutuhan tidak berasal dari Procurement Request quotation ini.');
                }
            }
        });
    }
}
