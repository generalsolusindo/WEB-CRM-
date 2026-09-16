<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'sales' && $user->is_active;
    }

    public function view(User $user, Contact $contact): bool
    {
        return $this->owns($user, $contact);
    }

    public function create(User $user): bool
    {
        return $user->role === 'sales' && $user->is_active;
    }

    public function update(User $user, Contact $contact): bool
    {
        return $this->owns($user, $contact);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->owns($user, $contact);
    }

    /** Gabungkan dua contact duplikat — keduanya harus milik Sales yang sama. */
    public function merge(User $user, Contact $keep, Contact $duplicate): bool
    {
        return $this->owns($user, $keep)
            && $this->owns($user, $duplicate)
            && $keep->id !== $duplicate->id;
    }

    private function owns(User $user, Contact $contact): bool
    {
        return $user->role === 'sales'
            && $user->is_active
            && $contact->created_by === $user->id;
    }
}
