<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use App\Jobs\SyncSubscriptionSeats;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * A user's membership (and role) in an organization.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property OrganizationRole $role
 * @property int|null $invited_by
 * @property Carbon|null $joined_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read User $user
 */
class Membership extends Pivot
{
    protected $table = 'organization_user';

    protected static function booted(): void
    {
        // Per-seat plans: the subscription quantity follows the member count.
        // Only Stripe customers can have a subscription, so skip the job for everyone else.
        $sync = function (self $membership): void {
            if ($membership->organization()->whereNotNull('stripe_id')->exists()) {
                SyncSubscriptionSeats::dispatch($membership->organization_id);
            }
        };

        static::created($sync);
        static::deleted($sync);
    }

    public $incrementing = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => OrganizationRole::class,
            'joined_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
