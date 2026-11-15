<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Members\ChangeMemberRole;
use App\Actions\Members\RemoveMember;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\UpdateMemberRequest;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request, Organization $organization, CurrentOrganization $current): Response
    {
        Gate::authorize('viewMembers', $organization);

        $user = $request->user();
        /** @var Membership $actor */
        $actor = $current->membership();
        $canInvite = $user->can('inviteMembers', $organization);

        $memberships = $organization->memberships()
            ->with('user:id,public_id,name,email')
            ->join('users', 'users.id', '=', 'organization_user.user_id')
            ->orderByRaw("case role when 'owner' then 0 when 'admin' then 1 when 'member' then 2 else 3 end")
            ->orderBy('users.name')
            ->select('organization_user.*')
            ->get();

        return Inertia::render('tenant/members/index', [
            'members' => $memberships->map(fn (Membership $membership) => [
                'public_id' => $membership->user->public_id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role->value,
                'joined_at' => $membership->joined_at?->toIso8601String(),
                'is_current_user' => $membership->user_id === $user->id,
                'can_remove' => $user->can('delete', $membership),
                'assignable_roles' => collect(OrganizationRole::cases())
                    ->filter(fn (OrganizationRole $role) => $user->can('update', [$membership, $role]))
                    ->map(fn (OrganizationRole $role) => $role->value)
                    ->values(),
            ]),
            'invitations' => $canInvite
                ? $organization->invitations()
                    ->whereNull('accepted_at')
                    ->with('inviter:id,name')
                    ->latest()
                    ->get()
                    ->map(fn (Invitation $invitation) => [
                        'public_id' => $invitation->public_id,
                        'email' => $invitation->email,
                        'role' => $invitation->role->value,
                        'invited_by' => $invitation->inviter?->name,
                        'expires_at' => $invitation->expires_at->toIso8601String(),
                        'is_expired' => $invitation->isExpired(),
                        'can_manage' => $user->can('manage', $invitation),
                    ])
                : [],
            'roles' => OrganizationRole::options(),
            'invitableRoles' => $canInvite
                ? array_map(fn (OrganizationRole $role) => $role->value, $actor->role->assignableRoles())
                : [],
            'canInvite' => $canInvite,
            'canTransferOwnership' => $user->can('transferOwnership', $organization),
            'canLeave' => $actor->role !== OrganizationRole::Owner || $organization->ownerCount() > 1,
            'seats' => [
                'used' => $organization->seatsUsed(),
                'limit' => $organization->seatLimit(),
            ],
        ]);
    }

    public function update(UpdateMemberRequest $request, Organization $organization, User $member, ChangeMemberRole $changeRole): RedirectResponse
    {
        $changeRole->handle($request->membership(), $request->role());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return back();
    }

    public function destroy(Organization $organization, User $member, RemoveMember $removeMember): RedirectResponse
    {
        $membership = $organization->membershipFor($member) ?? abort(404);
        Gate::authorize('delete', $membership);

        $removeMember->handle($membership);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was removed.', ['name' => $member->name])]);

        return back();
    }
}
