<?php

namespace App\Models\Scopes;

use App\Exceptions\MissingTenantContextException;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
class OrganizationScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $organizationId = app(CurrentOrganization::class)->id()
            ?? throw MissingTenantContextException::forModel($model::class);

        $builder->where($model->qualifyColumn('organization_id'), $organizationId);
    }
}
