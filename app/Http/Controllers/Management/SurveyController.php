<?php

namespace App\Http\Controllers\Management;

use App\Enums\SurveyStatus;
use App\Http\Controllers\Concerns\NormalizesDateRangeFilter;
use App\Http\Controllers\Controller;
use App\Models\Survey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Monitoring read-only untuk Management — tidak ada aksi/detail, cuma daftar lengkap. */
class SurveyController extends Controller
{
    use NormalizesDateRangeFilter;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAnyManagement', Survey::class);

        $filters = $this->normalizeDateRange($request->validate([
            'status' => ['nullable', Rule::enum(SurveyStatus::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]));

        $surveys = Survey::query()
            ->with('lead.contact:id,name,company_name')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $surveys->through(fn (Survey $survey) => [
            'id' => $survey->id,
            'code' => $survey->code,
            'customer' => $survey->lead?->contact?->name,
            'company' => $survey->lead?->contact?->company_name,
            'site_region' => $survey->site_region,
            'status' => $survey->status,
            'created_at' => $survey->created_at?->format('Y-m-d'),
        ]);

        return Inertia::render('Management/Surveys/Index', [
            'surveys' => $surveys,
            'filters' => [
                'status' => $filters['status'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
            'statusOptions' => SurveyStatus::options(),
        ]);
    }
}
