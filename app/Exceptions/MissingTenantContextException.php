<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a tenant-scoped model is queried or created without an active
 * organization. Fail closed: an unscoped query is a data-leak bug.
 *
 * Code that legitimately works across tenants (platform admin, console
 * commands) must opt out explicitly with `withoutGlobalScope(OrganizationScope::class)`
 * or run inside `CurrentOrganization::runAs()`.
 */
class MissingTenantContextException extends RuntimeException
{
    public static function forModel(string $model): self
    {
        return new self("No organization context is set while accessing tenant model [{$model}].");
    }
}
