<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a tenant route on a plan feature: ->middleware('feature:csv_export').
 */
class EnsureFeature
{
    public function __construct(protected CurrentOrganization $current) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $organization = $this->current->get() ?? abort(404);

        if ($organization->entitlements()->allows($feature)) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => __('Your plan does not include this feature.'), 'feature' => $feature], 402);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => __('Your plan does not include this feature. Upgrade to use it.')]);

        return redirect()->route('tenant.billing.show', ['organization' => $organization->slug]);
    }
}
