<?php

namespace App\Policies;

use App\Enums\LeadType;
use App\Models\Requirement;
use App\Models\User;

class RequirementPolicy
{
    public function create(User $user): bool
    {
        return $user->role === 'sales' && $user->is_active;
    }

    public function update(User $user, Requirement $requirement): bool
    {
        return $this->isEditableBy($user, $requirement);
    }

    public function delete(User $user, Requirement $requirement): bool
    {
        return $this->isEditableBy($user, $requirement);
    }

    /**
     * Kunci per-requirement (submitted_at), bukan per-Lead — supaya requirement baru
     * yang ditambahkan untuk pengajuan tambahan (addendum) tetap bisa diedit/dihapus
     * sebelum disubmit, walau Lead-nya sendiri sudah pernah disubmit sebelumnya.
     */
    private function isEditableBy(User $user, Requirement $requirement): bool
    {
        $requirement->loadMissing('lead');

        return $requirement->submitted_at === null
            && $user->role === 'sales'
            && $user->is_active
            && $requirement->lead->sales_id === $user->id
            && $requirement->lead->type === LeadType::Opportunity->value;
    }
}
