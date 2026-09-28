<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\NormalizesDateRangeFilter;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Monitoring absensi teknisi/surveyor untuk HR — read-only. Absen masuk & pulang tiap
 * orang cuma satu kali per survey/project (selfie + waktu unggah), jadi satu baris
 * = satu orang di satu pekerjaan; baris hanya ada kalau memang sudah ada absen masuk.
 */
class AttendanceController extends Controller
{
    use NormalizesDateRangeFilter;

    public function index(Request $request): Response
    {
        $filters = $this->normalizeDateRange($request->validate([
            'type' => ['nullable', 'in:project,survey'],
            'technician' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]));

        $checkIns = Attachment::query()
            ->where('category', 'checkin_selfie')
            ->whereIn('attachable_type', [(new Project)->getMorphClass(), (new Survey)->getMorphClass()])
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where(
                'attachable_type',
                $type === 'project' ? (new Project)->getMorphClass() : (new Survey)->getMorphClass(),
            ))
            ->when($filters['technician'] ?? null, fn ($q, $id) => $q->where('uploaded_by', $id))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->with([
                'uploader:id,name,vendor_id',
                'uploader.vendor:id,name',
                'attachable' => fn (MorphTo $morph) => $morph->morphWith([
                    Project::class => ['salesOrder:id,number,contact_id', 'salesOrder.contact:id,name,company_name'],
                    Survey::class => ['lead:id,contact_id', 'lead.contact:id,name,company_name'],
                ]),
            ])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $checkOuts = $this->checkOutsFor($checkIns->getCollection());

        $checkIns->through(function (Attachment $in) use ($checkOuts) {
            $job = $in->attachable;
            $isProject = $job instanceof Project;
            $out = $checkOuts->get($this->pairKey($in));
            $contact = $isProject ? $job?->salesOrder?->contact : $job?->lead?->contact;

            return [
                'id' => $in->id,
                'technician' => $in->uploader?->name,
                'vendor' => $in->uploader?->vendor?->name,
                'type' => $isProject ? 'project' : 'survey',
                'job_code' => $isProject
                    ? 'PRJ-'.str_pad((string) $job->id, 6, '0', STR_PAD_LEFT)
                    : $job?->code,
                'customer' => $contact?->name,
                'company' => $contact?->company_name,
                'check_in_at' => $in->created_at,
                'check_in_url' => Storage::disk('local')->temporaryUrl($in->file_path, now()->addDay()),
                'check_out_at' => $out?->created_at,
                'check_out_url' => $out ? Storage::disk('local')->temporaryUrl($out->file_path, now()->addDay()) : null,
                'duration_minutes' => $out ? (int) $in->created_at->diffInMinutes($out->created_at) : null,
            ];
        });

        return Inertia::render('Hr/Attendance/Index', [
            'attendance' => $checkIns,
            'filters' => [
                'type' => $filters['type'] ?? '',
                'technician' => $filters['technician'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
            'technicianOptions' => User::query()
                ->whereIn('id', Attachment::query()->where('category', 'checkin_selfie')->select('uploaded_by'))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /** Absen pulang milik pasangan (pekerjaan, orang) yang sedang tampil, dikunci "type:id:user". */
    private function checkOutsFor($checkIns)
    {
        if ($checkIns->isEmpty()) {
            return collect();
        }

        return Attachment::query()
            ->where('category', 'checkout_selfie')
            ->where(function ($query) use ($checkIns) {
                foreach ($checkIns as $in) {
                    $query->orWhere(fn ($pair) => $pair
                        ->where('attachable_type', $in->attachable_type)
                        ->where('attachable_id', $in->attachable_id)
                        ->where('uploaded_by', $in->uploaded_by));
                }
            })
            ->latest()
            ->get()
            ->keyBy(fn (Attachment $out) => $this->pairKey($out));
    }

    private function pairKey(Attachment $attachment): string
    {
        return "{$attachment->attachable_type}:{$attachment->attachable_id}:{$attachment->uploaded_by}";
    }
}
