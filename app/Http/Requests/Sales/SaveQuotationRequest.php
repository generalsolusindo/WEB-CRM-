<?php

namespace App\Http\Requests\Sales;

use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ($procurementRequest = $this->route('procurementRequest')) {
            return $user->can('create', [Quotation::class, $procurementRequest]);
        }

        if ($quotation = $this->route('quotation')) {
            return $user->can('update', $quotation);
        }

        return false;
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
            'lines.*.item_name' => ['nullable', 'string', 'max:255'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.qty' => ['nullable', 'numeric', 'min:0.01'],
            // Bukan Rule::in(Requirement::UNITS) yang ketat — ada data unit lama di database
            // (mis. "pcs", "Unit") di luar daftar baku itu. Kalau divalidasi ketat, edit yang
            // sama sekali tidak menyentuh field unit bisa gagal cuma gara-gara unit lama itu.
            'lines.*.unit' => ['nullable', 'string', 'max:50'],
            'lines.*.category' => ['nullable', Rule::in(['material', 'service', 'reimburse'])],
            'lines.*.sourcing_note' => ['nullable', 'string', 'max:2000'],
            'lines.*.selling_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
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
}
