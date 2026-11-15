<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only audit trail. Write through the Audit facade (App\Facades\Audit).
 *
 * Not tenant-scoped on purpose: platform-level entries (logins, admin actions)
 * have no organization. Read organization entries via Organization::auditLogs().
 *
 * @property int $id
 * @property string $public_id
 * @property int|null $organization_id
 * @property int|null $user_id
 * @property string|null $actor_name
 * @property string|null $actor_email
 * @property int|null $impersonator_id
 * @property string $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $subject_label
 * @property array<string, mixed>|null $metadata
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonImmutable $created_at
 * @property-read Organization|null $organization
 * @property-read User|null $user
 * @property-read User|null $impersonator
 */
class AuditLog extends Model
{
    use HasPublicId, MassPrunable;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit log entries are only removed by retention pruning.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Retention: `php artisan model:prune` (scheduled daily) removes old entries in bulk.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) config('audit.retention_days')));
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    /**
     * @return array{public_id: string, action: string, actor: string|null, actor_email: string|null, impersonator: string|null, subject_type: string|null, subject_label: string|null, metadata: array<string, mixed>, ip_address: string|null, created_at: string}
     */
    public function toDisplayArray(): array
    {
        return [
            'public_id' => $this->public_id,
            'action' => $this->action,
            'actor' => $this->actor_name,
            'actor_email' => $this->actor_email,
            'impersonator' => $this->impersonator?->name,
            'subject_type' => $this->subject_type,
            'subject_label' => $this->subject_label,
            'metadata' => $this->metadata ?? [],
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
