<?php

namespace App\Facades;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @method static AuditLog record(string $action, Organization|false|null $organization = null, ?Model $subject = null, array<string, mixed> $metadata = [], ?User $actor = null, ?string $subjectLabel = null)
 *
 * @see AuditLogger
 */
class Audit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AuditLogger::class;
    }
}
