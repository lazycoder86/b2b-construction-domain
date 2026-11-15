<?php

use App\Models\Organization;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'verified', 'tenant', 'feature:csv_export'])
        ->prefix('o/{organization}')
        ->get('exports', fn () => 'exported');
});

test('organizations with the feature pass', function () {
    $organization = Organization::factory()->create(['trial_ends_at' => now()->addDay()]);

    $this->actingAs(memberOf($organization))
        ->get("/o/{$organization->slug}/exports")
        ->assertOk()
        ->assertSee('exported');
});

test('organizations without the feature are sent to billing', function () {
    $organization = Organization::factory()->create();

    $this->actingAs(memberOf($organization))
        ->get("/o/{$organization->slug}/exports")
        ->assertRedirect(route('tenant.billing.show', $organization));
});

test('API clients get a 402', function () {
    $organization = Organization::factory()->create();

    $this->actingAs(memberOf($organization))
        ->getJson("/o/{$organization->slug}/exports")
        ->assertStatus(402)
        ->assertJson(['feature' => 'csv_export']);
});
