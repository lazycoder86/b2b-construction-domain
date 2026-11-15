<?php

namespace App\Http\Middleware;

use App\Support\Impersonation\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * /admin is for internal staff only: `is_platform_admin` + MFA, and never
 * while impersonating. Routes also use `password.confirm` (re-auth).
 */
class EnsurePlatformAdmin
{
    public function __construct(protected Impersonation $impersonation) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 404, not 403: don't advertise that the admin area exists.
        abort_unless($user?->is_platform_admin && ! $this->impersonation->active(), 404);

        if (config('admin.require_mfa') && ! $user->hasMultiFactorAuthentication()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Set up two-factor authentication or a passkey to use the admin area.')]);

            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
