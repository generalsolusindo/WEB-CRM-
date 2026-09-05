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
        $this->app->bind(
            \App\Services\Whatsapp\WhatsappGateway::class,
            \App\Services\Whatsapp\ClickToChatGateway::class,
        );
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

        // Manager mengelola akun Project Manager (bawahannya) — PM sendiri tidak boleh.
        Gate::define('manage-project-managers', fn (User $user): bool => $user->role === 'management' && $user->is_active);
    }
}
