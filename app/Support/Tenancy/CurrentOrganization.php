<?php

namespace App\Support\Tenancy;

use App\Enums\OrganizationRole;
use App\Models\Membership;
use App\Models\Organization;
use Closure;

/**
 * Holds the organization (tenant) for the current request or queued job.
 *
 * Registered as a *scoped* singleton, so it is reset between requests (Octane)
 * and between queued jobs.
 */
class CurrentOrganization
{
    protected ?Organization $organization = null;

    protected ?Membership $membership = null;

    public function set(Organization $organization, ?Membership $membership = null): void
    {
        $this->organization = $organization;
        $this->membership = $membership;
    }

    public function forget(): void
    {
        $this->organization = null;
        $this->membership = null;
    }

    public function get(): ?Organization
    {
        return $this->organization;
    }

    public function id(): ?int
    {
        return $this->organization?->id;
    }

    public function check(): bool
    {
        return $this->organization !== null;
    }

    public function membership(): ?Membership
    {
        return $this->membership;
    }

    public function role(): ?OrganizationRole
    {
        return $this->membership?->role;
    }

    /**
     * Run a callback with the given organization as the active tenant,
     * restoring the previous context afterwards.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(Organization $organization, Closure $callback): mixed
    {
        [$previousOrganization, $previousMembership] = [$this->organization, $this->membership];

        $this->set($organization);

        try {
            return $callback();
        } finally {
            $this->organization = $previousOrganization;
            $this->membership = $previousMembership;
        }
    }
}
