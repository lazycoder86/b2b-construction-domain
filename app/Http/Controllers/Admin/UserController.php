<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\User;
use App\Support\Impersonation\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $q = $request->validate(['q' => ['nullable', 'string', 'max:255']])['q'] ?? null;
        $search = $q !== null ? '%'.addcslashes($q, '%_').'%' : null;

        $users = User::query()
            ->withCount('memberships')
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->where('email', 'ilike', $search)
                ->orWhere('name', 'ilike', $search)
                ->orWhere('public_id', $q)))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user) => [
                'public_id' => $user->public_id,
                'name' => $user->name,
                'email' => $user->email,
                'verified' => $user->email_verified_at !== null,
                'is_platform_admin' => $user->is_platform_admin,
                'organizations' => $user->memberships_count,
                'created_at' => $user->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => ['q' => $q ?? ''],
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        return Inertia::render('admin/users/show', [
            'user' => [
                'public_id' => $user->public_id,
                'name' => $user->name,
                'email' => $user->email,
                'verified' => $user->email_verified_at !== null,
                'has_mfa' => $user->hasMultiFactorAuthentication(),
                'is_platform_admin' => $user->is_platform_admin,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'memberships' => $user->memberships()->with('organization:id,public_id,name,slug,status')->get()->map(fn (Membership $membership) => [
                'organization' => $membership->organization->name,
                'organization_public_id' => $membership->organization->public_id,
                'status' => $membership->organization->status->value,
                'role' => $membership->role->value,
            ]),
            'canImpersonate' => ! $user->is_platform_admin && ! $user->is($request->user()),
            'audit' => AuditLog::query()
                ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('impersonator_id', $user->id))
                ->with('impersonator:id,name')
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (AuditLog $entry) => $entry->toDisplayArray()),
        ]);
    }

    public function impersonate(Request $request, User $user, Impersonation $impersonation): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']])['reason'];

        abort_if($user->is_platform_admin || $user->is($request->user()), 403, __('This user cannot be impersonated.'));

        $impersonation->start($request->user(), $user, $reason);

        return redirect()->route('dashboard');
    }
}
