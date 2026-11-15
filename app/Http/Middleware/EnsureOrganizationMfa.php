<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces an organization's "require two-factor authentication" policy.
 * Members without TOTP or a passkey are sent to their security settings.
 */
class EnsureOrganizationMfa
{
    public function __construct(protected CurrentOrganization $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $organization = $this->current->get();

        if ($organization?->require_two_factor && ! $request->user()?->hasMultiFactorAuthentication()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __(':organization requires two-factor authentication. Set it up to continue.', ['organization' => $organization->name]),
            ]);

            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
