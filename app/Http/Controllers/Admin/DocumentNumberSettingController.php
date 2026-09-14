<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateDocumentNumberSettingRequest;
use App\Models\Invoice;
use App\Models\InvoiceNumberSetting;
use App\Models\Quotation;
use App\Models\QuotationNumberSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DocumentNumberSettingController extends Controller
{
    public function edit(): Response
    {
        $year = now()->year;

        return Inertia::render('Admin/DocumentNumbering', [
            'year' => $year,
            'invoice' => $this->summary(Invoice::query(), InvoiceNumberSetting::class, 'GS-INV', $year),
            'quotation' => $this->summary(Quotation::query()->whereNull('parent_quotation_id'), QuotationNumberSetting::class, 'GS-PN', $year),
        ]);
    }

    public function update(UpdateDocumentNumberSettingRequest $request): RedirectResponse
    {
        $model = $request->validated('document_type') === 'quotation' ? QuotationNumberSetting::class : InvoiceNumberSetting::class;

        $model::updateOrCreate(
            ['year' => now()->year],
            ['next_sequence' => $request->validated('next_sequence'), 'updated_by' => $request->user()->id],
        );

        return back()->with('success', 'Nomor berikutnya berhasil diatur.');
    }

    /** @return array<string, mixed> */
    private function summary(\Illuminate\Database\Eloquent\Builder $query, string $settingModel, string $middle, int $year): array
    {
        $lastSequence = $query
            ->where('number', 'like', "%/{$middle}/%/{$year}")
            ->pluck('number')
            ->map(fn (string $number) => (int) explode('/', $number)[0])
            ->max() ?? 0;

        $setting = $settingModel::query()->where('year', $year)->first();

        return [
            'lastSequence' => $lastSequence,
            'nextSequence' => $setting->next_sequence ?? ($lastSequence + 1),
            'hasCustomSetting' => $setting !== null,
        ];
    }
}
