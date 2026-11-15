<?php

use App\Http\Controllers\StripeWebhookController;
use App\Http\Middleware\EnsureFeature;
use App\Http\Middleware\EnsureOrganizationMfa;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleImpersonation;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveOrganization;
use App\Http\Middleware\SetSecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Laravel\Cashier\Http\Controllers\PaymentController;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Stripe: no session/CSRF (webhooks are verified by signature).
            Route::prefix(config('cashier.path'))->name('cashier.')->group(function (): void {
                Route::post('webhook', [StripeWebhookController::class, 'handleWebhook'])->name('webhook');
                Route::get('payment/{id}', [PaymentController::class, 'show'])->name('payment');
            });

            Route::middleware(['web', 'auth', 'verified', 'platform.admin'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));

            Route::middleware(['web', 'auth', 'verified', 'tenant', 'tenant.mfa'])
                ->prefix('o/{organization}')
                ->group(base_path('routes/tenant.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'tenant' => ResolveOrganization::class,
            'tenant.mfa' => EnsureOrganizationMfa::class,
            'feature' => EnsureFeature::class,
            'platform.admin' => EnsurePlatformAdmin::class,
        ]);

        // Resolve the tenant before route-model binding so tenant-scoped
        // models (e.g. /o/{organization}/projects/{project}) bind inside the scope.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: ResolveOrganization::class);

        $middleware->web(append: [
            SetSecurityHeaders::class,
            HandleImpersonation::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Reports to Sentry when SENTRY_LARAVEL_DSN is set; no-op otherwise.
        Integration::handles($exceptions);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
