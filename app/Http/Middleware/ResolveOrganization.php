<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Support\Tenancy\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\URL;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

use function Sentry\configureScope;

/**
 * Resolves the tenant from the `{organization}` slug in the URL.
 *
 * Runs before SubstituteBindings (see bootstrap/app.php) so tenant-scoped
 * models in the same route bind with the organization scope already active.
 */
class ResolveOrganization
{
    public function __construct(protected CurrentOrganization $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $parameter = $route?->parameter('organization');

        $organization = match (true) {
            $parameter instanceof Organization => $parameter,
            is_string($parameter) => Organization::query()->where('slug', $parameter)->first(),
            default => null,
        };

        $user = $request->user();
        $membership = $organization !== null && $user !== null
            ? $organization->membershipFor($user)
            : null;

        // Same response for "doesn't exist" and "not a member": don't leak which slugs exist.
        abort_if($organization === null || $membership === null, 404);

        if ($organization->status === OrganizationStatus::PendingDeletion && $membership->role === OrganizationRole::Owner) {
            return redirect()->route('organizations.deletion.show', ['organization' => $organization->slug]);
        }

        abort_unless($organization->isActive(), 403, __('This organization is not active.'));

        $route->setParameter('organization', $organization);
        $this->current->set($organization, $membership);

        // Added to every log entry and carried into queued jobs dispatched from this request.
        Context::add('organization', $organization->public_id);
        configureScope(fn (Scope $scope) => $scope->setTag('organization', $organization->public_id));
        URL::defaults(['organization' => $organization->slug]);

        $user->switchOrganization($organization);

        return $next($request);
    }
}
