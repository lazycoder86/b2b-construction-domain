<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An invitation for an email address to join an organization with a role.
 *
 * @property int $id
 * @property string $public_id
 * @property int $organization_id
 * @property string $email
 * @property OrganizationRole $role
 * @property string $token_hash
 * @property int|null $invited_by
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $inviter
 */
#[Hidden(['id', 'organization_id', 'token_hash', 'invited_by'])]
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use BelongsToOrganization, HasFactory, HasPublicId;

    protected $guarded = ['id', 'public_id', 'organization_id', 'token_hash', 'accepted_at'];

    /**
     * Plain token, only available right after it was (re)generated.
     */
    public ?string $plainToken = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => OrganizationRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Look up an invitation by its plain token. Invitations are opened from an
     * email link, outside any tenant context, hence the explicit scope bypass.
     */
    public static function findByToken(string $token): ?self
    {
        return static::withoutGlobalScope(OrganizationScope::class)
            ->with('organization')
            ->where('token_hash', static::hashToken($token))
            ->first();
    }

    /**
     * Issue a new token and expiry. The previous link stops working.
     */
    public function regenerateToken(): string
    {
        $this->plainToken = Str::random(48);
        $this->token_hash = static::hashToken($this->plainToken);
        $this->expires_at = now()->addDays(config('tenancy.invitation_expiry_days'));

        return $this->plainToken;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isFor(User $user): bool
    {
        return Str::lower($this->email) === Str::lower($user->email);
    }

    public function markAccepted(): void
    {
        $this->forceFill(['accepted_at' => now()])->save();
    }
}
