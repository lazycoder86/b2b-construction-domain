<?php

namespace App\Models\Concerns;

use App\Exceptions\MissingTenantContextException;
use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Use on every model whose table has an `organization_id` column.
 *
 * - All queries are scoped to the current organization (fails closed when none is set).
 * - `organization_id` is filled automatically on create.
 * - Moving a record to another organization is forbidden.
 *
 * tests/Feature/Tenancy/TenantModelsTest.php enforces that tenant tables use this trait.
 *
 * @property int $organization_id
 * @property-read Organization $organization
 *
 * @mixin Model
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('organization_id') === null) {
                $model->setAttribute(
                    'organization_id',
                    app(CurrentOrganization::class)->id()
                        ?? throw MissingTenantContextException::forModel($model::class),
                );
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('organization_id')) {
                throw new LogicException('Records cannot be moved between organizations.');
            }
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
