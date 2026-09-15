<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Manajemen User" — satu tempat untuk melihat & mengelola SEMUA akun lintas role
 * (kecuali vendor, lihat User::ADMIN_ASSIGNABLE_ROLES), termasuk akun yang aslinya
 * dibuat lewat alur lain (Project Manager via Management, Teknisi via Procurement) —
 * supaya reset password / nonaktifkan akun tidak perlu lewat terminal lagi.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $search = trim((string) $request->query('search', ''));
        $role = $request->query('role', '');

        $users = User::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")))
            ->when($role !== '', fn ($query) => $query->where('role', $role))
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'role_label' => User::ADMIN_ASSIGNABLE_ROLES[$user->role] ?? ucfirst($user->role),
                'is_active' => $user->is_active,
                'is_self' => $user->id === $request->user()->id,
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search, 'role' => $role],
            'roleOptions' => User::ADMIN_ASSIGNABLE_ROLES,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('Admin/Users/Form', [
            'roleOptions' => User::ADMIN_ASSIGNABLE_ROLES,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil dibuat.');
    }

    public function edit(User $user): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('Admin/Users/Form', [
            'targetUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ],
            'roleOptions' => User::ADMIN_ASSIGNABLE_ROLES,
            'isSelf' => $user->id === request()->user()->id,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $wantsActive = $data['is_active'] ?? true;

        // Ini sudah cukup untuk mencegah lockout total: siapa pun yang menjalankan aksi ini
        // wajib administrator aktif (lihat UserPolicy), jadi kalau target BUKAN diri sendiri,
        // si pelaku sendiri otomatis jadi bukti masih ada admin aktif lain yang tersisa.
        // Skenario "menonaktifkan admin aktif terakhir" cuma bisa terjadi lewat menonaktifkan
        // diri sendiri, yang sudah diblokir di bawah ini terlepas dari jumlah admin lain.
        if (! $wantsActive && $user->id === $request->user()->id) {
            return back()->with('error', 'Tidak bisa menonaktifkan akun sendiri.');
        }

        $user->fill([
            'name' => $data['name'],
            'username' => $data['username'],
            'is_active' => $wantsActive,
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil diperbarui.');
    }
}
