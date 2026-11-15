<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a user who belongs to the organization with the given role.
 */
function memberOf(Organization $organization, OrganizationRole $role = OrganizationRole::Member, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $organization->addMember($user, $role);

    return $user;
}

/**
 * Give the organization a Cashier subscription row (what Stripe webhooks would create).
 */
function subscribeTo(Organization $organization, string $stripePrice = 'price_pro_monthly', string $status = 'active', ?CarbonInterface $endsAt = null, int $quantity = 1): Subscription
{
    $organization->forceFill(['stripe_id' => $organization->stripe_id ?? 'cus_'.Str::random(10)])->save();

    $subscription = $organization->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_'.Str::random(10),
        'stripe_status' => $status,
        'stripe_price' => $stripePrice,
        'quantity' => $quantity,
        'ends_at' => $endsAt,
    ]);

    $organization->refreshEntitlements();

    return $subscription;
}

/**
 * A platform admin with MFA set up, whose password was just confirmed (admin pages require it).
 */
function platformAdmin(array $attributes = []): User
{
    $admin = User::factory()->withTwoFactor()->create(['is_platform_admin' => true, ...$attributes]);
    test()->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

    return $admin;
}
