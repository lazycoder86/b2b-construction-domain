<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Raw usage events for metered/usage-based pricing (e.g. API calls, records created).
 * Write through App\Support\Billing\UsageMeter.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $metric
 * @property int $quantity
 * @property CarbonImmutable $recorded_at
 * @property array<string, mixed>|null $metadata
 */
class UsageRecord extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'recorded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
