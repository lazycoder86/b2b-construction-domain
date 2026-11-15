<?php

namespace App\Providers;

use App\Contracts\Billing\BillingGateway;
use App\Contracts\Billing\SubscriptionProvider;
use App\Models\Organization;
use App\Support\Audit\AuditLogger;
use App\Support\Billing\CashierBillingGateway;
use App\Support\Billing\CashierSubscriptionProvider;
use App\Support\Billing\PlanCatalog;
use App\Support\Impersonation\Impersonation;
use App\Support\Tenancy\CurrentOrganization;
use App\Support\Tenancy\QueueTenancy;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use Laravel\Pennant\Feature;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped: reset per request (Octane) and per queued job.
        $this->app->scoped(CurrentOrganization::class);
        $this->app->scoped(QueueTenancy::class);

        $this->app->scoped(Impersonation::class);
        $this->app->scoped(AuditLogger::class);

        $this->app->singleton(PlanCatalog::class);
        $this->app->bind(SubscriptionProvider::class, CashierSubscriptionProvider::class);
        $this->app->bind(BillingGateway::class, CashierBillingGateway::class);

        // Webhook + payment routes are registered in bootstrap/app.php (idempotent controller).
        Cashier::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        QueueTenancy::register($this->app->make(Dispatcher::class));

        $this->configureBilling();
        $this->configureFeatureFlags();
        $this->configureObservability();
    }

    /**
     * Pennant flags (config/features.php), scoped to the current organization.
     */
    protected function configureFeatureFlags(): void
    {
        Feature::resolveScopeUsing(fn () => app(CurrentOrganization::class)->get());

        foreach ((array) config('features.flags') as $name => $flag) {
            $default = (bool) ($flag['default'] ?? false);
            Feature::define($name, fn (?Organization $organization) => $organization !== null && $default);
        }
    }

    protected function configureObservability(): void
    {
        URL::forceHttps($this->app->isProduction());

        // /up also checks that the database answers.
        Event::listen(DiagnosingHealth::class, fn () => DB::select('select 1'));
    }

    /**
     * The organization, not the user, is the Stripe customer.
     */
    protected function configureBilling(): void
    {
        Cashier::useCustomerModel(Organization::class);

        // Keep access while Stripe retries a failed renewal (dunning). Access ends
        // when Stripe cancels the subscription or marks it unpaid.
        Cashier::keepPastDueSubscriptionsActive();
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
