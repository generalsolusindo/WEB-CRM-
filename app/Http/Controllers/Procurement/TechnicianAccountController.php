<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreTechnicianAccountRequest;
use App\Http\Requests\Procurement\UpdateTechnicianAccountRequest;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class TechnicianAccountController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('manage-technicians');

        $search = trim((string) $request->query('search', ''));

        $technicians = User::query()
            ->where('role', 'technician')
            ->with('vendor:id,name')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => $user->is_active,
                'origin' => $user->vendor ? $user->vendor->name : 'Internal / HO',
            ]);

        return Inertia::render('Procurement/Technicians/Index', [
            'technicians' => $technicians,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('manage-technicians');

        return Inertia::render('Procurement/Technicians/Form', [
            'vendorOptions' => $this->vendorOptions(),
        ]);
    }

    public function store(StoreTechnicianAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => 'technician',
            'vendor_id' => $data['vendor_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return redirect()->route('procurement.technicians.index')
            ->with('success', 'Akun teknisi/surveyor berhasil dibuat.');
    }

    public function edit(User $technician): Response
    {
        Gate::authorize('manage-technicians');
        abort_unless($technician->role === 'technician', 404);

        return Inertia::render('Procurement/Technicians/Form', [
            'technician' => $technician->only('id', 'name', 'email', 'phone', 'vendor_id', 'is_active'),
            'vendorOptions' => $this->vendorOptions(),
        ]);
    }

    public function update(UpdateTechnicianAccountRequest $request, User $technician): RedirectResponse
    {
        abort_unless($technician->role === 'technician', 404);

        $data = $request->validated();

        $technician->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'vendor_id' => $data['vendor_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (! empty($data['password'])) {
            $technician->password = Hash::make($data['password']);
        }

        $technician->save();

        return redirect()->route('procurement.technicians.index')
            ->with('success', 'Akun teknisi/surveyor berhasil diperbarui.');
    }

    /** @return array<int, array{value: int, label: string}> */
    private function vendorOptions(): array
    {
        return Vendor::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Vendor $vendor) => ['value' => $vendor->id, 'label' => $vendor->name])
            ->all();
    }
}
