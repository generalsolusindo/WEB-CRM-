<?php

namespace App\Actions\Operational;

use App\Enums\SowStatus;
use App\Models\Project;
use App\Models\Sow;
use App\Models\User;
use App\Services\DocumentNumber;
use App\Support\SowDefaults;
use Illuminate\Support\Facades\DB;

class SaveSowDraft
{
    public function __construct(private DocumentNumber $documentNumber) {}

    /** @param array<string, mixed> $data */
    public function handle(Project $project, User $user, array $data): Sow
    {
        return DB::transaction(function () use ($project, $user, $data) {
            $sow = $project->sow;
            $isFirstSave = $sow === null;

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
                    'status' => $sow?->status ?? SowStatus::Draft->value,
                    'created_by' => $sow?->created_by ?? $user->id,
                    'updated_by' => $user->id,
                ],
            );

            if ($isFirstSave) {
                $saved->scopeSections()->create([
                    'position' => 1,
                    'title' => 'Pengadaan Material',
                    'content' => SowDefaults::materialScopeContent($project->loadMissing('actualProcurements')),
                ]);
            }

            return $saved;
        });
    }
}
