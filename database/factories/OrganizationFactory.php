<?php

namespace Database\Factories;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::limit(Str::slug($name), 40, '').'-'.Str::lower(Str::random(6)),
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => OrganizationStatus::Suspended]);
    }

    /**
     * Attach the given user (or a new one) as a member with the given role.
     */
    public function withMember(?User $user = null, OrganizationRole $role = OrganizationRole::Member): static
    {
        return $this->afterCreating(function (Organization $organization) use ($user, $role): void {
            $organization->addMember($user ?? User::factory()->create(), $role);
        });
    }

    public function withOwner(?User $user = null): static
    {
        return $this->withMember($user, OrganizationRole::Owner);
    }
}
