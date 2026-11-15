<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use App\Models\Concerns\HasPublicId;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_organization_id
 * @property bool $is_platform_admin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization|null $currentOrganization
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['id', 'current_organization_id', 'password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicId, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_platform_admin' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Organization, $this, Membership, 'membership'>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
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
     * The organization the user last worked in. Only a hint for redirects;
     * the URL slug is what determines the tenant for a request.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function belongsToOrganization(Organization $organization): bool
    {
        return $this->memberships()->where('organization_id', $organization->id)->exists();
    }

    public function roleIn(Organization $organization): ?OrganizationRole
    {
        return $organization->membershipFor($this)?->role;
    }

    /**
     * Organization to send the user to after login, or null if they have none.
     */
    public function homeOrganization(): ?Organization
    {
        $current = $this->currentOrganization;

        if ($current !== null && $current->isActive() && $this->belongsToOrganization($current)) {
            return $current;
        }

        return $this->organizations()
            ->where('status', 'active')
            ->orderBy('organizations.name')
            ->first();
    }

    public function switchOrganization(Organization $organization): void
    {
        if ($this->current_organization_id !== $organization->id) {
            $this->forceFill(['current_organization_id' => $organization->id])->saveQuietly();
        }
    }

    /**
     * Whether the user has a second factor: confirmed TOTP or a passkey.
     */
    public function hasMultiFactorAuthentication(): bool
    {
        return $this->hasEnabledTwoFactorAuthentication() || $this->hasPasskeysEnabled();
    }
}
