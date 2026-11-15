<?php

namespace App\Http\Middleware;

use App\Support\Impersonation\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * While a platform admin impersonates a user:
 *  - the session ends automatically after config('admin.impersonation_minutes');
 *  - account-security, billing and other irreversible actions are blocked.
 */
class HandleImpersonation
{
    /**
     * Route names (wildcards allowed) that are off-limits during impersonation.
     *
     * @var list<string>
     */
    public const BLOCKED_ROUTES = [
        'admin.*',
        'security.*',
        'user-password.*',
        'password.confirm*',
        'two-factor.*',
        'passkey*',
        'profile.update',
        'profile.destroy',
        'profile.export',
        'tenant.billing.subscribe',
        'tenant.billing.portal',
        'tenant.members.transfer-ownership',
        'tenant.settings.security.update',
        'tenant.organization.export*',
        'tenant.organization.destroy',
        'organizations.restore',
        'organizations.store',
        'invitations.accept',
        'invitations.decline',
    ];

    public function __construct(protected Impersonation $impersonation) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->impersonation->active()) {
            return $next($request);
        }

        if ($this->impersonation->expired()) {
            $admin = $this->impersonation->stop('expired');
            Inertia::flash('toast', ['type' => 'info', 'message' => __('Impersonation session expired.')]);

            return $admin !== null ? redirect()->route('admin.dashboard') : redirect()->route('login');
        }

        if ($request->route()?->named(...self::BLOCKED_ROUTES)) {
            abort(403, __('Not available while impersonating a user.'));
        }

        return $next($request);
    }
}
