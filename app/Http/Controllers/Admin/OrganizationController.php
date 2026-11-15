<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationStatus;
use App\Facades\Audit;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Support\Billing\Plan;
use App\Support\Billing\PlanCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(OrganizationStatus::class)],
        ]);

        $search = isset($filters['q']) ? '%'.addcslashes($filters['q'], '%_').'%' : null;

        $organizations = Organization::query()
            ->withCount('memberships')
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'ilike', $search)
                ->orWhere('slug', 'ilike', $search)
                ->orWhere('public_id', $filters['q'])
                ->orWhere('stripe_id', $filters['q'])
                ->orWhereHas('members', fn ($query) => $query->where('email', 'ilike', $search))))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Organization $organization) => [
                'public_id' => $organization->public_id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'status' => $organization->status->value,
                'plan' => $organization->entitlements()->plan->name,
                'billing_status' => $organization->entitlements()->status->value,
                'members' => $organization->memberships_count,
                'created_at' => $organization->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/organizations/index', [
            'organizations' => $organizations,
            'filters' => ['q' => $filters['q'] ?? '', 'status' => $filters['status'] ?? ''],
        ]);
    }

    public function show(Organization $organization, PlanCatalog $plans): Response
    {
        $entitlements = $organization->entitlements();
        $subscription = $organization->subscription(config('billing.subscription_type'));
        $flags = (array) config('features.flags');
        $flagValues = Feature::for($organization)->values(array_keys($flags));

        return Inertia::render('admin/organizations/show', [
            'organization' => [
                'public_id' => $organization->public_id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'status' => $organization->status->value,
                'require_two_factor' => $organization->require_two_factor,
                'stripe_id' => $organization->stripe_id,
                'trial_ends_at' => $organization->trial_ends_at?->toIso8601String(),
                'deletion_scheduled_at' => $organization->deletion_scheduled_at?->toIso8601String(),
                'created_at' => $organization->created_at?->toIso8601String(),
                'entitlement_overrides' => $organization->entitlement_overrides ?? (object) [],
            ],
            'entitlements' => $entitlements->toArray(),
            'subscription' => $subscription === null ? null : [
                'stripe_id' => $subscription->stripe_id,
                'status' => $subscription->stripe_status,
                'price' => $subscription->stripe_price,
                'quantity' => $subscription->quantity,
                'ends_at' => $subscription->ends_at?->toIso8601String(),
            ],
            'members' => $organization->memberships()->with('user:id,public_id,name,email')->get()->map(fn (Membership $membership) => [
                'public_id' => $membership->user->public_id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role->value,
            ]),
            'pendingInvitations' => $organization->invitations()->pending()->count(),
            'flags' => collect($flags)->map(fn (array $flag, string $key) => [
                'key' => $key,
                'description' => $flag['description'] ?? '',
                'active' => (bool) ($flagValues[$key] ?? false),
                'default' => (bool) ($flag['default'] ?? false),
            ])->values(),
            'plans' => array_map(fn (Plan $plan) => ['key' => $plan->key, 'name' => $plan->name], $plans->all()),
            'audit' => $organization->auditLogs()->with('impersonator:id,name')->latest('id')->limit(50)->get()
                ->map(fn (AuditLog $entry) => $entry->toDisplayArray()),
        ]);
    }

    public function updateStatus(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([OrganizationStatus::Active->value, OrganizationStatus::Suspended->value])],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        abort_if($organization->status === OrganizationStatus::PendingDeletion, 422, __('Organizations pending deletion are restored by their owners.'));

        $from = $organization->status->value;
        $organization->forceFill(['status' => $validated['status']])->save();

        Audit::record(
            $validated['status'] === OrganizationStatus::Suspended->value ? 'admin.organization_suspended' : 'admin.organization_activated',
            $organization,
            $organization,
            ['from' => $from, 'reason' => $validated['reason']],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status updated.')]);

        return back();
    }

    public function extendTrial(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:90']]);

        $base = $organization->trial_ends_at?->isFuture() ? $organization->trial_ends_at : now();
        $endsAt = $base->addDays((int) $validated['days']);

        $organization->forceFill(['trial_ends_at' => $endsAt, 'trial_reminder_sent_at' => null])->save();

        Audit::record('admin.trial_extended', $organization, $organization, ['days' => (int) $validated['days'], 'trial_ends_at' => $endsAt->toIso8601String()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trial extended.')]);

        return back();
    }

    public function updateEntitlements(Request $request, Organization $organization, PlanCatalog $plans): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['nullable', 'string', Rule::in(array_map(fn (Plan $plan) => $plan->key, $plans->all()))],
            'features' => ['array'],
            'features.*' => ['string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'seats' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $overrides = array_filter([
            'plan' => $validated['plan'] ?? null,
            'features' => array_values(array_unique($validated['features'] ?? [])) ?: null,
            'limits' => isset($validated['seats']) ? ['seats' => (int) $validated['seats']] : null,
        ], fn ($value) => $value !== null);

        $before = $organization->entitlement_overrides;
        $organization->forceFill(['entitlement_overrides' => $overrides === [] ? null : $overrides])->save();

        Audit::record('admin.entitlements_updated', $organization, $organization, [
            'from' => $before,
            'to' => $overrides === [] ? null : $overrides,
            'reason' => $validated['reason'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Entitlements updated.')]);

        return back();
    }

    public function updateFlag(Request $request, Organization $organization, string $flag): RedirectResponse
    {
        abort_unless(array_key_exists($flag, (array) config('features.flags')), 404);

        $request->validate(['state' => ['required', Rule::in(['on', 'off', 'default'])]]);
        $state = $request->string('state')->value();
        $feature = Feature::for($organization);

        if ($state === 'on') {
            $feature->activate($flag);
        } elseif ($state === 'off') {
            $feature->deactivate($flag);
        } else {
            // Back to the default from config/features.php.
            $feature->forget($flag);
        }

        Audit::record('admin.flag_changed', $organization, $organization, ['flag' => $flag, 'state' => $state]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Flag updated.')]);

        return back();
    }
}
