<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreVendorAccountRequest;
use App\Http\Requests\Procurement\UpdateVendorAccountRequest;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class VendorAccountController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('manage-vendor-accounts');

        $search = trim((string) $request->query('search', ''));

        $accounts = User::query()
            ->where('role', 'vendor')
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
                'vendor' => $user->vendor?->name,
            ]);

        return Inertia::render('Procurement/VendorAccounts/Index', [
            'accounts' => $accounts,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('manage-vendor-accounts');

        return Inertia::render('Procurement/VendorAccounts/Form', [
            'vendorOptions' => $this->availableVendorOptions(),
        ]);
    }

    public function store(StoreVendorAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => 'vendor',
            'vendor_id' => $data['vendor_id'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return redirect()->route('procurement.vendor-accounts.index')
            ->with('success', 'Akun PIC vendor berhasil dibuat.');
    }

    public function edit(User $vendorAccount): Response
    {
        Gate::authorize('manage-vendor-accounts');
        abort_unless($vendorAccount->role === 'vendor', 404);

        return Inertia::render('Procurement/VendorAccounts/Form', [
            'account' => $vendorAccount->only('id', 'name', 'email', 'phone', 'vendor_id', 'is_active'),
            'vendorOptions' => $this->availableVendorOptions($vendorAccount->vendor_id),
        ]);
    }

    public function update(UpdateVendorAccountRequest $request, User $vendorAccount): RedirectResponse
    {
        abort_unless($vendorAccount->role === 'vendor', 404);

        $data = $request->validated();

        $vendorAccount->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'vendor_id' => $data['vendor_id'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (! empty($data['password'])) {
            $vendorAccount->password = Hash::make($data['password']);
        }

        $vendorAccount->save();

        return redirect()->route('procurement.vendor-accounts.index')
            ->with('success', 'Akun PIC vendor berhasil diperbarui.');
    }

    /** @return array<int, array{value: int, label: string}> */
    private function availableVendorOptions(?int $includeVendorId = null): array
    {
        return Vendor::query()
            ->where(fn ($query) => $query
                ->whereDoesntHave('technicians', fn ($q) => $q->where('role', 'vendor'))
                ->when($includeVendorId, fn ($q) => $q->orWhere('id', $includeVendorId)))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Vendor $vendor) => ['value' => $vendor->id, 'label' => $vendor->name])
            ->all();
    }
}
