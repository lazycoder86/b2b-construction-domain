<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\Impersonation\Impersonation;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Laravel\Pennant\Feature;

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
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            // Closures are resolved at render time, after the tenant middleware has run.
            'currentOrganization' => fn () => $this->currentOrganization(),
            'organizations' => fn () => $request->user()?->organizations()
                ->where('status', 'active')
                ->orderBy('organizations.name')
                ->get(['organizations.public_id', 'organizations.name', 'organizations.slug'])
                ->map(fn (Organization $organization) => [
                    'public_id' => $organization->public_id,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                ])
                ->all() ?? [],
            'impersonation' => fn () => $this->impersonation(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{public_id: string, name: string, slug: string, role: string|null, permissions: list<string>, billing: array<string, mixed>, flags: list<string>}|null
     */
    protected function currentOrganization(): ?array
    {
        $current = app(CurrentOrganization::class);
        $organization = $current->get();

        if ($organization === null) {
            return null;
        }

        return [
            'public_id' => $organization->public_id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'role' => $current->role()?->value,
            // For UI only (hide buttons). The server re-checks every action.
            'permissions' => $current->role()?->permissions() ?? [],
            // For UI only (badges, upsells). Gate features server-side with the `feature:` middleware.
            'billing' => $organization->entitlements()->toArray(),
            // Active Pennant flags (config/features.php) for this organization.
            'flags' => array_keys(array_filter(Feature::for($organization)->values(array_keys((array) config('features.flags'))))),
        ];
    }

    /**
     * @return array{impersonator: string|null, expires_at: string|null}|null
     */
    protected function impersonation(): ?array
    {
        $impersonation = app(Impersonation::class);

        if (! $impersonation->active()) {
            return null;
        }

        return [
            'impersonator' => $impersonation->impersonator()?->name,
            'expires_at' => $impersonation->expiresAt()?->toIso8601String(),
        ];
    }
}
