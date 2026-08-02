<?php

namespace App\Providers;

use App\Events\InvoicePaid;
use App\Events\InvoicePdfGenerated;
use App\Events\InvoiceSent;
use App\Listeners\PublishInvoiceDomainEvent;
use App\Models\Invoice;
use App\Observers\InvoiceObserver;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

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
        RateLimiter::for('invoice-api', function (Request $request) {
            $userId = null;

            if ($token = $request->bearerToken()) {
                $accessToken = PersonalAccessToken::findToken($token);

                if ($accessToken) {
                    $userId = $accessToken->tokenable_id;
                }
            }

            return Limit::perMinute($userId ? 60 : 5)
                ->by($userId ?: $request->ip());
        });

        Invoice::observe(InvoiceObserver::class);

        Event::listen([
            InvoicePdfGenerated::class,
            InvoiceSent::class,
            InvoicePaid::class,
        ], PublishInvoiceDomainEvent::class);
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

        Password::defaults(
            fn (): ?Password => app()->isProduction()
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
