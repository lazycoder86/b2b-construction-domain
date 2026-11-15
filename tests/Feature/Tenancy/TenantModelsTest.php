<?php

use App\Models\AuditLog;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Membership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Guard rail: any model whose table has an `organization_id` column must use
 * BelongsToOrganization, otherwise its queries are not tenant-scoped.
 */
const TENANT_SCOPE_EXEMPT = [
    // Membership rows are looked up across organizations (e.g. the org switcher).
    Membership::class,
    // Audit entries can be platform-level (organization_id null); read via Organization::auditLogs().
    AuditLog::class,
];

test('every model with an organization_id column uses BelongsToOrganization', function () {
    $models = collect(File::allFiles(app_path('Models')))
        ->map(fn (SplFileInfo $file) => 'App\\Models\\'.Str::of($file->getRelativePathname())->replace(['/', '.php'], ['\\', ''])->toString())
        ->filter(fn (string $class) => class_exists($class) && is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract());

    expect($models)->not->toBeEmpty();

    $unscoped = $models
        ->reject(fn (string $class) => in_array($class, TENANT_SCOPE_EXEMPT, true))
        ->filter(fn (string $class) => Schema::hasColumn((new $class)->getTable(), 'organization_id'))
        ->reject(fn (string $class) => in_array(BelongsToOrganization::class, class_uses_recursive($class), true))
        ->values()
        ->all();

    expect($unscoped)->toBe([], 'These models have organization_id but are not tenant-scoped: '.implode(', ', $unscoped));
});
