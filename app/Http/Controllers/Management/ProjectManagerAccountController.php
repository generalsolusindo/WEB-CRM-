<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\StoreProjectManagerAccountRequest;
use App\Http\Requests\Management\UpdateProjectManagerAccountRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ProjectManagerAccountController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('manage-project-managers');

        $search = trim((string) $request->query('search', ''));

        $projectManagers = User::query()
            ->where('role', 'project_manager')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => $user->is_active,
            ]);

        return Inertia::render('Management/ProjectManagers/Index', [
            'projectManagers' => $projectManagers,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('manage-project-managers');

        return Inertia::render('Management/ProjectManagers/Form');
    }

    public function store(StoreProjectManagerAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();

        User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => 'project_manager',
            'is_active' => $data['is_active'] ?? true,
        ]);

        return redirect()->route('management.project-managers.index')
            ->with('success', 'Akun Project Manager berhasil dibuat.');
    }

    public function edit(User $projectManager): Response
    {
        Gate::authorize('manage-project-managers');
        abort_unless($projectManager->role === 'project_manager', 404);

        return Inertia::render('Management/ProjectManagers/Form', [
            'projectManager' => $projectManager->only('id', 'name', 'username', 'email', 'phone', 'is_active'),
        ]);
    }

    public function update(UpdateProjectManagerAccountRequest $request, User $projectManager): RedirectResponse
    {
        abort_unless($projectManager->role === 'project_manager', 404);

        $data = $request->validated();

        $projectManager->fill([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (! empty($data['password'])) {
            $projectManager->password = Hash::make($data['password']);
        }

        $projectManager->save();

        return redirect()->route('management.project-managers.index')
            ->with('success', 'Akun Project Manager berhasil diperbarui.');
    }
}
