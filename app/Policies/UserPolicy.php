<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function view(User $user, User $target): bool
    {
        return $this->isAdministrator($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function update(User $user, User $target): bool
    {
        return $this->isAdministrator($user);
    }

    /**
     * Sengaja TIDAK ada ability delete() — akun user direferensikan di puluhan tabel lain
     * (created_by, sales_id, uploaded_by, dst). Menghapus baris User bisa merusak riwayat
     * data atau kena restrict FK di tempat tak terduga. Nonaktifkan (is_active=false) adalah
     * cara yang aman & setara secara efek (akun tidak bisa dipakai login lagi).
     */
    private function isAdministrator(User $user): bool
    {
        return $user->role === 'administrator' && $user->is_active;
    }
}
