<?php

namespace App\Providers;

use App\Mail\BrevoTransport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
        $this->registerMailTransport();
        Gate::define('manage-reference-data', fn (User $user): bool => $user->role === User::ROLE_ADMIN);
    }

    /**
     * Send mail through the Brevo transactional API instead of its SMTP relay.
     */
    protected function registerMailTransport(): void
    {
        Mail::extend('brevo', function (array $config): BrevoTransport {
            return new BrevoTransport(
                key: is_string($config['key'] ?? null) ? $config['key'] : null,
                timeout: (int) ($config['timeout'] ?? 30),
            );
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
