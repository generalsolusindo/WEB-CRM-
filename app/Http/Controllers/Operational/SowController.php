<?php

namespace App\Http\Controllers\Operational;

use App\Actions\Operational\AddSowScopeSection;
use App\Actions\Operational\DeleteSowScopeSection;
use App\Actions\Operational\MoveSowScopeSection;
use App\Actions\Operational\RestartSowSignatures;
use App\Actions\Operational\SaveSowDraft;
use App\Actions\Operational\SubmitSowForReview;
use App\Actions\Operational\UpdateSowScopeSection;
use App\Actions\Sow\SignSow;
use App\Enums\SowStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\SaveSowRequest;
use App\Http\Requests\Operational\SaveSowScopeSectionRequest;
use App\Http\Requests\Operational\UploadSowImageRequest;
use App\Http\Requests\Operational\UploadSowScopeImageRequest;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Sow;
use App\Models\SowScopeSection;
use App\Models\User;
use App\Services\UserSignature;
use App\Support\SowDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class SowController extends Controller
{
    public function edit(Project $project): Response
    {
        Gate::authorize('viewSow', $project);

        $project->load([
            'salesOrder:id,contact_id,quotation_id',
            'salesOrder.contact:id,name,company_name,address',
            'salesOrder.quotation:id,lead_id',
            'salesOrder.quotation.lead:id,pic_name,pic_position,pic_phone',
            'vendor:id,name,contact_person,phone',
            'actualProcurements' => fn ($q) => $q->orderBy('id'),
            'sow.attachments' => fn ($q) => $q->where('category', 'sow_background')->latest(),
            'sow.scopeSections.attachments',
            'sow.technician:id,name',
            'sow.hrContentReviewedBy:id,name',
        ]);

        $sow = $project->sow;
        $lead = $project->salesOrder?->quotation?->lead;

        return Inertia::render('Operational/Projects/Sow', [
            'project' => [
                'id' => $project->id,
                'number' => 'PRJ-'.str_pad((string) $project->id, 6, '0', STR_PAD_LEFT),
                'customer' => $project->salesOrder?->contact?->name,
                'company' => $project->salesOrder?->contact?->company_name,
            ],
            'vendor' => $project->vendor ? [
                'name' => $project->vendor->name,
                'contact_person' => $project->vendor->contact_person,
                'phone' => $project->vendor->phone,
            ] : null,
            'materials' => $project->actualProcurements->map(fn ($item) => [
                'item_name' => $item->item_name,
                'qty' => $item->qty,
                'unit' => $item->unit,
            ]),
            'technicianOptions' => User::query()
                ->where(fn ($q) => $q->where('role', 'technician')->orWhere('can_technician', true))
                ->where('vendor_id', $project->vendor_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'sow' => $sow ? [
                'id' => $sow->id,
                'status' => $sow->status,
                'status_label' => SowStatus::from($sow->status)->label(),
                'number' => $sow->number,
                'project_name' => $sow->project_name,
                'site_location' => $sow->site_location,
                'client_name' => $sow->client_name,
                'execution_date' => $sow->execution_date,
                'background' => $sow->background,
                'scope_sections' => $sow->scopeSections->map(fn (SowScopeSection $s) => [
                    'id' => $s->id,
                    'title' => $s->title,
                    'content' => $s->content,
                    'images' => $s->attachments->map(fn (Attachment $a) => [
                        'id' => $a->id,
                        'url' => Storage::disk('local')->temporaryUrl($a->file_path, now()->addDay()),
                    ]),
                ]),
                'responsibilities' => $sow->responsibilities,
                'schedule_duration' => $sow->schedule_duration,
                'schedule_start_date' => $sow->schedule_start_date?->format('Y-m-d'),
                'schedule_end_date' => $sow->schedule_end_date?->format('Y-m-d'),
                'safety' => $sow->safety,
                'payment_terms' => $sow->payment_terms,
                'output' => $sow->output,
                'warranty' => $sow->warranty,
                'notes' => $sow->notes,
                'closing' => $sow->closing,
                'section_visibility' => $sow->section_visibility ?? [],
                'custom_sections' => $sow->custom_sections ?? [],
                'technician_id' => $sow->technician_id,
                'technician_team_note' => $sow->technician_team_note,
                'client_pic_name' => $sow->client_pic_name,
                'client_pic_phone' => $sow->client_pic_phone,
                'hr_content_reviewed_by' => $sow->hrContentReviewedBy?->name,
                'hr_content_review_notes' => $sow->hr_content_review_notes,
                'images' => $sow->attachments->map(fn (Attachment $a) => [
                    'id' => $a->id,
                    'url' => Storage::disk('local')->temporaryUrl($a->file_path, now()->addDay()),
                ]),
            ] : [
                'id' => null,
                'status' => null,
                'status_label' => null,
                'number' => null,
                'project_name' => null,
                'site_location' => $project->salesOrder?->contact?->address,
                'client_name' => $project->salesOrder?->contact?->company_name ?: $project->salesOrder?->contact?->name,
                'execution_date' => null,
                'background' => null,
                'scope_sections' => [],
                'responsibilities' => SowDefaults::responsibilities(),
                'schedule_duration' => null,
                'schedule_start_date' => null,
                'schedule_end_date' => null,
                'safety' => SowDefaults::safety(),
                'payment_terms' => SowDefaults::paymentTerms(),
                'output' => SowDefaults::output(),
                'warranty' => SowDefaults::warranty(),
                'notes' => SowDefaults::notes(),
                'closing' => SowDefaults::closing($project, null),
                'section_visibility' => [],
                'custom_sections' => [],
                'technician_id' => null,
                'technician_team_note' => null,
                'client_pic_name' => $lead?->pic_name,
                'client_pic_phone' => $lead?->pic_phone,
                'images' => [],
            ],
            'signatures' => $sow ? [
                'technician' => $sow->technician_signature,
                'vendor' => $sow->vendor_signature,
                'admin' => $sow->admin_signature,
                'director' => $sow->director_signature,
            ] : null,
            'canEdit' => request()->user()->can('manageSow', $project),
            'canSignOperational' => $sow ? request()->user()->can('signAsAdmin', $sow) : false,
            'canRestartSignatures' => $sow ? request()->user()->can('restartSignatures', $sow) : false,
        ]);
    }

    public function update(SaveSowRequest $request, Project $project, SaveSowDraft $action): RedirectResponse
    {
        $action->handle($project, $request->user(), $request->validated());

        return back()->with('success', 'Draft SOW tersimpan.');
    }

    public function storeImage(UploadSowImageRequest $request, Project $project): RedirectResponse
    {
        $sow = $project->sow;
        abort_unless($sow, 404, 'Simpan draft SOW terlebih dahulu sebelum upload gambar.');

        foreach ($request->file('images', []) as $image) {
            $sow->attachments()->create([
                'category' => 'sow_background',
                'file_path' => $image->store('sow-backgrounds'),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        return back()->with('success', 'Gambar tersimpan.');
    }

    public function destroyImage(Project $project, Attachment $image): RedirectResponse
    {
        Gate::authorize('manageSowAssets', $project);
        abort_unless($image->attachable_type === Sow::class && $image->attachable_id === $project->sow?->id, 404);

        Storage::disk('local')->delete($image->file_path);
        $image->delete();

        return back()->with('success', 'Gambar dihapus.');
    }

    public function storeScopeSection(SaveSowScopeSectionRequest $request, Project $project, AddSowScopeSection $action): RedirectResponse
    {
        $sow = $project->sow;
        abort_unless($sow, 404, 'Simpan draft SOW terlebih dahulu.');

        $action->handle($sow, $request->validated('title'), $request->validated('content'));

        return back()->with('success', 'Sub-bab ruang lingkup ditambahkan.');
    }

    public function updateScopeSection(SaveSowScopeSectionRequest $request, Project $project, SowScopeSection $scopeSection, UpdateSowScopeSection $action): RedirectResponse
    {
        abort_unless($scopeSection->sow_id === $project->sow?->id, 404);

        $action->handle($scopeSection, $request->validated('title'), $request->validated('content'));

        return back()->with('success', 'Sub-bab ruang lingkup tersimpan.');
    }

    public function destroyScopeSection(Project $project, SowScopeSection $scopeSection, DeleteSowScopeSection $action): RedirectResponse
    {
        Gate::authorize('manageSowAssets', $project);
        abort_unless($scopeSection->sow_id === $project->sow?->id, 404);

        $action->handle($scopeSection);

        return back()->with('success', 'Sub-bab ruang lingkup dihapus.');
    }

    public function moveScopeSection(Project $project, SowScopeSection $scopeSection, MoveSowScopeSection $action): RedirectResponse
    {
        Gate::authorize('manageSowAssets', $project);
        abort_unless($scopeSection->sow_id === $project->sow?->id, 404);
        $direction = request()->input('direction') === 'up' ? 'up' : 'down';

        $action->handle($scopeSection, $direction);

        return back()->with('success', 'Urutan sub-bab diperbarui.');
    }

    public function storeScopeImage(UploadSowScopeImageRequest $request, Project $project, SowScopeSection $scopeSection): RedirectResponse
    {
        abort_unless($scopeSection->sow_id === $project->sow?->id, 404);

        foreach ($request->file('images', []) as $image) {
            $scopeSection->attachments()->create([
                'category' => 'sow_scope_image',
                'file_path' => $image->store('sow-scope-images'),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        return back()->with('success', 'Gambar tersimpan.');
    }

    public function destroyScopeImage(Project $project, SowScopeSection $scopeSection, Attachment $image): RedirectResponse
    {
        Gate::authorize('manageSowAssets', $project);
        abort_unless($scopeSection->sow_id === $project->sow?->id, 404);
        abort_unless($image->attachable_type === SowScopeSection::class && $image->attachable_id === $scopeSection->id, 404);

        Storage::disk('local')->delete($image->file_path);
        $image->delete();

        return back()->with('success', 'Gambar dihapus.');
    }

    public function submit(Project $project, SubmitSowForReview $action): RedirectResponse
    {
        $sow = $project->sow;
        abort_unless($sow, 404);
        Gate::authorize('submit', $sow);

        $action->handle($sow);

        return back()->with('success', 'SOW dikirim ke HR untuk direview.');
    }

    public function print(Project $project): View
    {
        Gate::authorize('viewSow', $project);

        $project->load([
            'salesOrder.contact:id,name,company_name,address',
            'vendor:id,name,contact_person,phone',
            'actualProcurements' => fn ($q) => $q->orderBy('id'),
            'sow.technician:id,name,phone',
            'sow.vendorSignedBy:id,name',
            'sow.adminSignedBy:id,name',
            'sow.directorSignedBy:id,name',
            'sow.attachments' => fn ($q) => $q->where('category', 'sow_background'),
            'sow.scopeSections.attachments',
        ]);
        abort_unless($project->sow, 404);

        $imageUrls = $project->sow->attachments->map(
            fn ($a) => Storage::disk('local')->temporaryUrl($a->file_path, now()->addHour()),
        );

        $scopeSectionImageUrls = $project->sow->scopeSections->mapWithKeys(fn ($section) => [
            $section->id => $section->attachments->map(
                fn ($a) => Storage::disk('local')->temporaryUrl($a->file_path, now()->addHour()),
            ),
        ]);

        return view('operational.sows.print', [
            'project' => $project,
            'sow' => $project->sow,
            'imageUrls' => $imageUrls,
            'scopeSectionImageUrls' => $scopeSectionImageUrls,
        ]);
    }

    public function signOperational(Sow $sow, SignSow $action, UserSignature $userSignature): RedirectResponse
    {
        Gate::authorize('signAsAdmin', $sow);

        $action->handle($sow, request()->user(), 'admin', $userSignature->dataUrl(request()->user()));

        return redirect()->route('operational.projects.show', $sow->project_id)
            ->with('success', 'SOW berhasil ditanda tangani — diteruskan ke Project Manager.');
    }

    public function restartSignatures(Sow $sow, RestartSowSignatures $action): RedirectResponse
    {
        Gate::authorize('restartSignatures', $sow);
        $action->handle($sow);

        return back()->with('success', 'Proses tanda tangan Teknisi & PIC Vendor diulang.');
    }
}
