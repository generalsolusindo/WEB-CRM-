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
            // Nullable (bukan required) — line lama boleh kirim payload parsial (cuma field
            // yang berubah, sisanya jatuh ke nilai lama, lihat UpdateQuotation::handle()).
            // Line BARU (tanpa procurement_request_line_id) wajib isi lengkap — dicek di
            // withValidator() karena syaratnya beda tergantung baris lama/baru.
            'lines.*.procurement_request_line_id' => ['nullable', 'integer'],
            'lines.*.item_name' => ['nullable', 'string', 'max:255'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.qty' => ['nullable', 'numeric', 'min:0.01'],
            // Bukan Rule::in(Requirement::UNITS) yang ketat — ada data unit lama di database
            // (mis. "pcs", "Unit") di luar daftar baku itu.
            'lines.*.unit' => ['nullable', 'string', 'max:50'],
            'lines.*.category' => ['nullable', Rule::in(['material', 'service', 'reimburse'])],
            'lines.*.sourcing_note' => ['nullable', 'string', 'max:2000'],
            // Cuma dipakai untuk line baru — untuk line lama, cost_price TETAP diambil dari
            // data quotation yang sudah ada, input client di sini diabaikan sepenuhnya supaya
            // Sales tidak bisa memalsukan margin pada item yang sudah divalidasi Procurement.
            'lines.*.cost_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
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

            $seen = [];

            foreach ($this->input('lines', []) as $i => $line) {
                $prLineId = $line['procurement_request_line_id'] ?? null;

                if ($prLineId !== null) {
                    if (! in_array((int) $prLineId, $validPrLineIds, true)) {
                        $validator->errors()->add("lines.{$i}.procurement_request_line_id", 'Baris Procurement tidak valid untuk quotation ini.');
                    } elseif (in_array((int) $prLineId, $seen, true)) {
                        $validator->errors()->add("lines.{$i}.procurement_request_line_id", 'Baris Procurement ini sudah dipakai di baris lain.');
                    } else {
                        $seen[] = (int) $prLineId;
                    }

                    continue;
                }

                // Line baru (tanpa baris Procurement asal) — tidak ada nilai lama untuk
                // jatuh ke fallback, jadi field intinya wajib diisi lengkap di sini.
                foreach (['item_name', 'qty', 'unit', 'category', 'cost_price'] as $field) {
                    if (($line[$field] ?? '') === '' || $line[$field] === null) {
                        $validator->errors()->add("lines.{$i}.{$field}", match ($field) {
                            'item_name' => 'Nama item wajib diisi untuk item baru.',
                            'qty' => 'Qty wajib diisi untuk item baru.',
                            'unit' => 'Unit wajib diisi untuk item baru.',
                            'category' => 'Kategori wajib diisi untuk item baru.',
                            'cost_price' => 'Harga beli wajib diisi untuk item baru.',
                        });
                    }
                }
            }
        });
    }
}
