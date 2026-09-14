<?php

namespace App\Http\Requests\Admin;

use App\Models\Invoice;
use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDocumentNumberSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'administrator';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::in(['invoice', 'quotation'])],
            'next_sequence' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $year = now()->year;
            $middle = $this->input('document_type') === 'quotation' ? 'GS-PN' : 'GS-INV';
            $query = $this->input('document_type') === 'quotation'
                ? Quotation::query()->whereNull('parent_quotation_id')
                : Invoice::query();

            $lastSequence = $query
                ->where('number', 'like', "%/{$middle}/%/{$year}")
                ->pluck('number')
                ->map(fn (string $number) => (int) explode('/', $number)[0])
                ->max() ?? 0;

            if ((int) $this->input('next_sequence') <= $lastSequence) {
                $validator->errors()->add(
                    'next_sequence',
                    "Nomor berikutnya harus lebih besar dari nomor terakhir yang sudah ada tahun ini ({$lastSequence})."
                );
            }
        });
    }
}
