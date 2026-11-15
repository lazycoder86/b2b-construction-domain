<?php

use App\Enums\OrganizationRole;
use App\Exceptions\MissingTenantContextException;
use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Models\User;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Widget;

beforeEach(function () {
    Widget::createTable();

    $this->orgA = Organization::factory()->create();
    $this->orgB = Organization::factory()->create();
    $this->tenancy = app(CurrentOrganization::class);
});

function widgetFor(Organization $organization, string $name): Widget
{
    return app(CurrentOrganization::class)->runAs($organization, fn () => Widget::create(['name' => $name]));
}

test('queries are scoped to the current organization', function () {
    widgetFor($this->orgA, 'a-widget');
    widgetFor($this->orgB, 'b-widget');

    $this->tenancy->set($this->orgA);

    expect(Widget::pluck('name')->all())->toBe(['a-widget']);
});

test('queries fail closed without an organization context', function () {
    Widget::query()->get();
})->throws(MissingTenantContextException::class);

test('creating fails closed without an organization context', function () {
    Widget::create(['name' => 'orphan']);
})->throws(MissingTenantContextException::class);

test('organization_id is filled from the current organization', function () {
    $widget = widgetFor($this->orgA, 'auto');

    expect($widget->organization_id)->toBe($this->orgA->id);
});

test('records cannot be moved to another organization', function () {
    $widget = widgetFor($this->orgA, 'pinned');

    $this->tenancy->set($this->orgA);
    $widget->update(['organization_id' => $this->orgB->id]);
})->throws(LogicException::class);

test('the scope can be bypassed explicitly for cross-tenant work', function () {
    widgetFor($this->orgA, 'a');
    widgetFor($this->orgB, 'b');

    expect(Widget::withoutGlobalScope(OrganizationScope::class)->count())->toBe(2);
});

test('runAs restores the previous context', function () {
    $this->tenancy->set($this->orgA);

    $inner = $this->tenancy->runAs($this->orgB, fn () => $this->tenancy->id());

    expect($inner)->toBe($this->orgB->id)
        ->and($this->tenancy->id())->toBe($this->orgA->id);
});

test('tenant models in tenant routes cannot be bound across organizations', function () {
    Route::middleware(['web', 'auth', 'verified', 'tenant'])
        ->prefix('o/{organization}')
        ->get('widgets/{widget}', fn (Organization $organization, Widget $widget) => $widget->name);

    $user = User::factory()->create();
    $this->orgA->addMember($user, OrganizationRole::Member);

    $own = widgetFor($this->orgA, 'mine');
    $foreign = widgetFor($this->orgB, 'theirs');
    $this->tenancy->forget();

    $this->actingAs($user)
        ->get("/o/{$this->orgA->slug}/widgets/{$own->id}")
        ->assertOk()
        ->assertSee('mine');

    // A widget from org B, requested through org A's URL, must not resolve.
    $this->actingAs($user)
        ->get("/o/{$this->orgA->slug}/widgets/{$foreign->id}")
        ->assertNotFound();
});
