<?php

namespace App\Http\Middleware;

use App\Services\Dashboard\MenuBadges;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ] : null,
                'isProjectLeader' => $user && $user->role === 'technician'
                    ? DB::table('project_technicians')
                        ->where('technician_id', $user->id)
                        ->where('is_leader', true)
                        ->exists()
                    : false,
                'notifications' => $user ? [
                    'unread_count' => $user->notifications()->unread()->count(),
                    'items' => $user->notifications()
                        ->latest()
                        ->take(10)
                        ->get(['id', 'type', 'message', 'related_type', 'related_id', 'read_at', 'created_at']),
                ] : null,
                'menuBadges' => $user ? app(MenuBadges::class)->for($user) : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'whatsappUrl' => fn () => $request->session()->get('whatsappUrl'),
            ],
        ];
    }
}
