<?php

namespace Database\Factories;

use App\Enums\OrganizationRole;
use App\Models\Invitation;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The plain token is available as $invitation->plainToken after creation.
 *
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => OrganizationRole::Member,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Invitation $invitation): void {
            // regenerateToken() also sets the expiry; keep one chosen explicitly (see expired()).
            $explicitExpiry = $invitation->getAttribute('expires_at');
            $invitation->regenerateToken();

            if ($explicitExpiry !== null) {
                $invitation->setAttribute('expires_at', $explicitExpiry);
            }
        });
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function accepted(): static
    {
        return $this->afterCreating(fn (Invitation $invitation) => $invitation->markAccepted());
    }
}
