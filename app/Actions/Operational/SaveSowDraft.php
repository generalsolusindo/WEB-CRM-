<?php

namespace App\Actions\Operational;

use App\Enums\SowStatus;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Sow;
use App\Models\User;
use App\Services\DocumentNumber;
use App\Services\Notifications\Notify;
use App\Support\SowDefaults;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SaveSowDraft
{
    public function __construct(private DocumentNumber $documentNumber, private Notify $notify) {}

    /** @param array<string, mixed> $data */
    public function handle(Project $project, User $user, array $data): Sow
    {
        return DB::transaction(function () use ($project, $user, $data) {
            $scopeSections = $data['scope_sections'] ?? null;
            $customSections = $data['custom_sections'] ?? [];
            unset($data['scope_sections']);
            $data['custom_sections'] = array_values($customSections);
            $sow = Sow::query()->where('project_id', $project->id)->lockForUpdate()->first();
            $isFirstSave = $sow === null;
            if ($isFirstSave && $scopeSections === []) {
                $scopeSections = null;
            }

            // SOW sudah dikirim (atau lebih jauh) tapi isinya diedit lagi — tanda tangan
            // yang sudah dikumpulkan tidak lagi sah untuk isi yang baru, jadi seluruh
            // proses review & tanda tangan direset dan harus dimulai ulang dari Draft.
            $wasSentBefore = $sow !== null
                && ! in_array($sow->status, [SowStatus::Draft->value, SowStatus::RejectedByHr->value], true);

            if ($isFirstSave) {
                if (empty($data['number'])) {
                    $data['number'] = $this->documentNumber->nextSowNumber();
                }

                $data['responsibilities'] = ($data['responsibilities'] ?? '') ?: SowDefaults::responsibilities();
                $data['safety'] = ($data['safety'] ?? '') ?: SowDefaults::safety();
                $data['payment_terms'] = ($data['payment_terms'] ?? '') ?: SowDefaults::paymentTerms();
                $data['output'] = ($data['output'] ?? '') ?: SowDefaults::output();
                $data['warranty'] = ($data['warranty'] ?? '') ?: SowDefaults::warranty();
                $data['notes'] = ($data['notes'] ?? '') ?: SowDefaults::notes();
                $data['closing'] = ($data['closing'] ?? '') ?: SowDefaults::closing($project, $data['project_name'] ?? null);
            }

            $saved = Sow::updateOrCreate(
                ['project_id' => $project->id],
                [
                    ...$data,
                    'status' => $wasSentBefore ? SowStatus::Draft->value : ($sow?->status ?? SowStatus::Draft->value),
                    'created_by' => $sow?->created_by ?? $user->id,
                    'updated_by' => $user->id,
                    ...($wasSentBefore ? [
                        'submitted_at' => null,
                        'hr_content_reviewed_by' => null,
                        'hr_content_reviewed_at' => null,
                        'hr_content_review_notes' => null,
                        'technician_signature' => null,
                        'technician_signed_at' => null,
                        'vendor_signature' => null,
                        'vendor_signed_at' => null,
                        'vendor_signed_by' => null,
                        'hr_signature_reviewed_by' => null,
                        'hr_signature_reviewed_at' => null,
                        'hr_signature_review_notes' => null,
                        'admin_signature' => null,
                        'admin_signed_at' => null,
                        'admin_signed_by' => null,
                        'director_signature' => null,
                        'director_signed_at' => null,
                        'director_signed_by' => null,
                    ] : []),
                ],
            );

            if ($isFirstSave) {
                $saved->scopeSections()->create([
                    'position' => 1,
                    'title' => 'Pengadaan Material',
                    'content' => SowDefaults::materialScopeContent($project->loadMissing('actualProcurements')),
                ]);
            }

            if ($scopeSections !== null) {
                $keptIds = [];

                foreach (array_values($scopeSections) as $position => $sectionData) {
                    $section = ! empty($sectionData['id'])
                        ? $saved->scopeSections()->whereKey($sectionData['id'])->first()
                        : null;

                    $values = [
                        'position' => $position + 1,
                        'title' => $sectionData['title'],
                        'content' => $sectionData['content'] ?? null,
                    ];

                    $section ? $section->update($values) : $section = $saved->scopeSections()->create($values);
                    $keptIds[] = $section->id;
                }

                $removed = $saved->scopeSections()->whereNotIn('id', $keptIds)->with('attachments')->get();
                foreach ($removed as $section) {
                    foreach ($section->attachments as $attachment) {
                        Storage::disk('local')->delete($attachment->file_path);
                        $attachment->delete();
                    }
                    $section->delete();
                }
            }

            if ($wasSentBefore) {
                $this->notifyStakeholdersOfReset($sow, $saved, $project);
            }

            return $saved;
        });
    }

    private function notifyStakeholdersOfReset(Sow $oldSow, Sow $saved, Project $project): void
    {
        foreach ([
            'sow.pending_hr_review', 'sow.pending_technician_signature', 'sow.pending_vendor_signature',
            'sow.pending_hr_verification', 'sow.pending_admin_signature', 'sow.pending_director_signature',
        ] as $type) {
            $this->notify->resolve($type, $saved);
        }

        $customer = $project->salesOrder?->contact?->name ?? 'customer';
        $message = "SOW {$saved->number} ({$customer}) diedit ulang oleh Operasional setelah dikirim — proses review & tanda tangan dimulai dari awal.";

        $recipients = User::query()->where('role', 'hr')->where('is_active', true)->get();

        if ($oldSow->technician_id && in_array($oldSow->status, SowStatus::visibleToTechnicianValues(), true)) {
            $recipients->push(User::query()->find($oldSow->technician_id));
        }

        if (in_array($oldSow->status, SowStatus::visibleToVendorValues(), true)) {
            $recipients->push(User::query()->where('role', 'vendor')->where('vendor_id', $project->vendor_id)->first());
        }

        if ($oldSow->status === SowStatus::PendingDirectorSignature->value) {
            $recipients = $recipients->merge(
                $project->delegated_to
                    ? User::query()->where('id', $project->delegated_to)->where('is_active', true)->get()
                    : User::query()->where('role', 'management')->where('is_active', true)->get(),
            );
        }

        foreach ($recipients->filter()->unique('id') as $recipient) {
            Notification::updateOrCreate(
                [
                    'user_id' => $recipient->id,
                    'type' => 'sow.reset_for_edit',
                    'related_type' => $saved->getMorphClass(),
                    'related_id' => $saved->id,
                ],
                ['message' => $message, 'is_sent' => true, 'read_at' => null],
            );
        }
    }
}
