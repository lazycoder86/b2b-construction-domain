<?php

namespace App\Actions\Users;

use App\Enums\OrganizationRole;
use App\Facades\Audit;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteUser
{
    /**
     * Delete a user account without orphaning organizations.
     *
     * - Sole owner of an org that has other members: blocked; ownership must be transferred first.
     * - Sole member of an org: the org is deleted along with the account.
     *
     * Note: log the user out *before* calling this. Logging out afterwards saves
     * the (deleted) model again when it rotates the remember token.
     *
     * @throws ValidationException
     */
    public function handle(User $user, string $errorKey = 'password'): void
    {
        $this->ensureCanDelete($user, $errorKey);

        DB::transaction(function () use ($user): void {
            // Organizations nobody else belongs to would be orphaned: remove them.
            $this->soleMemberOrganizations($user)->each(function (Organization $organization) use ($user): void {
                Audit::record('organization.deleted', $organization, $organization, ['reason' => 'owner_account_deleted'], actor: $user);
                $organization->delete();
            });

            $user->delete();
        });
    }

    /**
     * @throws ValidationException
     */
    public function ensureCanDelete(User $user, string $errorKey = 'password'): void
    {
        $blocking = $this->organizationsBlockingDeletion($user);

        if ($blocking !== []) {
            throw ValidationException::withMessages([
                $errorKey => __('You are the only owner of :organizations. Transfer ownership or remove the other members before deleting your account.', [
                    'organizations' => implode(', ', $blocking),
                ]),
            ]);
        }

        // These organizations would be deleted with the account, but Stripe would keep billing them.
        $subscribed = $this->soleMemberOrganizations($user)
            ->filter(fn (Organization $organization) => $organization->subscribed(config('billing.subscription_type')))
            ->map(fn (Organization $organization) => $organization->name)
            ->values()
            ->all();

        if ($subscribed !== []) {
            throw ValidationException::withMessages([
                $errorKey => __('Cancel the subscription for :organizations before deleting your account.', [
                    'organizations' => implode(', ', $subscribed),
                ]),
            ]);
        }
    }

    /**
     * Organizations nobody but this user belongs to.
     *
     * @return Collection<int, Organization>
     */
    protected function soleMemberOrganizations(User $user): Collection
    {
        return Organization::query()
            ->whereHas('memberships', fn ($query) => $query->where('user_id', $user->id))
            ->whereDoesntHave('memberships', fn ($query) => $query->where('user_id', '!=', $user->id))
            ->get();
    }

    /**
     * Names of organizations where the user is the only owner but not the only member.
     *
     * @return array<int, string>
     */
    public function organizationsBlockingDeletion(User $user): array
    {
        return Organization::query()
            ->whereHas('memberships', fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('role', OrganizationRole::Owner))
            ->whereDoesntHave('memberships', fn ($query) => $query
                ->where('user_id', '!=', $user->id)
                ->where('role', OrganizationRole::Owner))
            ->whereHas('memberships', fn ($query) => $query->where('user_id', '!=', $user->id))
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $organization) => $organization->name)
            ->values()
            ->all();
    }
}
