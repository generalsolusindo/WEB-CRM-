<?php

namespace App\Providers;

use App\Models\Attachment;
use App\Models\Payment;
use App\Models\User;
use App\Observers\AttachmentObserver;
use App\Observers\PaymentObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Payment::observe(PaymentObserver::class);
        Attachment::observe(AttachmentObserver::class);

        // Procurement mengelola akun teknisi/surveyor (internal maupun milik vendor).
        Gate::define('manage-technicians', fn (User $user): bool => $user->role === 'procurement' && $user->is_active);
    }
}
