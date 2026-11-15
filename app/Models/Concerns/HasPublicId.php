<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Adds an externally visible ULID (`public_id`) next to the internal bigint key.
 *
 * Expose `public_id` in URLs, APIs and the frontend; keep `id` internal.
 *
 * @mixin Model
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('public_id') === null) {
                $model->setAttribute('public_id', (string) Str::ulid());
            }
        });
    }

    public static function findByPublicId(string $publicId): ?static
    {
        return static::query()->where('public_id', $publicId)->first();
    }
}
