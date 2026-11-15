<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Models\Concerns\HasPublicId;
use App\Models\Scopes\OrganizationScope;
use App\Support\Billing\EntitlementResolver;
use App\Support\Billing\Entitlements;
use Carbon\CarbonImmutable;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Cashier\Billable;
use Laravel\Pennant\Feature;

/**
 * A tenant. The organization (not the user) owns data and pays for the subscription.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $slug
 * @property OrganizationStatus $status
 * @property bool $require_two_factor
 * @property CarbonImmutable|null $deletion_scheduled_at
 * @property array<string, mixed>|null $settings
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable|null $trial_reminder_sent_at
 * @property array<string, mixed>|null $entitlement_overrides {"plan"?: string, "features"?: string[], "limits"?: {key: int|null}}, validated by EntitlementResolver
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable(['name', 'slug', 'settings'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use Billable, HasFactory, HasPublicId, SoftDeletes;

    /**
     * Resolved once per model instance; call refreshEntitlements() after changes.
     */
    protected ?Entitlements $resolvedEntitlements = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    protected static function booted(): void
    {
        // Pennant values aren't foreign-keyed to organizations; remove them with the row.
        static::forceDeleted(function (self $organization): void {
            Feature::for($organization)->forget(array_keys((array) config('features.flags')));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'require_two_factor' => 'boolean',
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
            'deletion_scheduled_at' => 'datetime',
            'trial_reminder_sent_at' => 'datetime',
            'entitlement_overrides' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsToMany<User, $this, Membership, 'membership'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(Membership::class)
            ->as('membership')
            ->withPivot(['id', 'role', 'invited_by', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * The relation already constrains organization_id, so the tenant scope is
     * redundant here and is removed to allow use outside a tenant context
     * (e.g. accepting an invitation, platform admin).
     *
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /**
     * Same reasoning as invitations(): the relation already constrains organization_id.
     *
     * @return HasMany<UsageRecord, $this>
     */
    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * What this organization may do (plan features and limits). See config/billing.php.
     */
    public function entitlements(): Entitlements
    {
        return $this->resolvedEntitlements ??= app(EntitlementResolver::class)->for($this);
    }

    public function refreshEntitlements(): Entitlements
    {
        $this->resolvedEntitlements = null;
        $this->unsetRelation('subscriptions');

        return $this->entitlements();
    }

    /**
     * Maximum members (active + pending invitations); null means unlimited.
     */
    public function seatLimit(): ?int
    {
        return $this->entitlements()->limit('seats');
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function owners(): HasMany
    {
        return $this->memberships()->where('role', OrganizationRole::Owner);
    }

    /**
     * Stripe customer email: the first owner's address (receipts, dunning emails).
     */
    public function stripeEmail(): ?string
    {
        return $this->owners()->oldest('id')->first()?->user->email;
    }

    public function stripeName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, string>
     */
    public function stripeMetadata(): array
    {
        return ['organization_public_id' => $this->public_id];
    }

    public function seatsUsed(): int
    {
        return $this->memberships()->count() + $this->invitations()->pending()->count();
    }

    public function hasAvailableSeat(): bool
    {
        $limit = $this->seatLimit();

        return $limit === null || $this->seatsUsed() < $limit;
    }

    public function membershipFor(User $user): ?Membership
    {
        return $this->memberships()->where('user_id', $user->id)->first();
    }

    public function addMember(User $user, OrganizationRole $role, ?User $invitedBy = null): Membership
    {
        return $this->memberships()->create([
            'user_id' => $user->id,
            'role' => $role,
            'invited_by' => $invitedBy?->id,
            'joined_at' => now(),
        ]);
    }

    public function ownerCount(): int
    {
        return $this->owners()->count();
    }

    public function isActive(): bool
    {
        return $this->status->isAccessible();
    }
}
