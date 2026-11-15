<?php

use App\Models\Organization;
use App\Support\Tenancy\CurrentOrganization;
use App\Support\Tenancy\QueueTenancy;
use Tests\Fixtures\RecordsTenantJob;

beforeEach(function () {
    RecordsTenantJob::$seen = [];
});

test('queued jobs run with the organization that dispatched them', function () {
    $organization = Organization::factory()->create();
    $current = app(CurrentOrganization::class);
    $current->set($organization);

    RecordsTenantJob::dispatch();

    expect(RecordsTenantJob::$seen)->toBe([$organization->id])
        ->and($current->id())->toBe($organization->id);
});

test('jobs dispatched without a tenant run without one', function () {
    RecordsTenantJob::dispatch();

    expect(RecordsTenantJob::$seen)->toBe([null]);
});

test('the organization id is written into the job payload', function () {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    // Build a payload exactly the way a real queue connection does before pushing.
    $queue = app('queue')->connection('database');
    $payload = (new ReflectionMethod($queue, 'createPayload'))->invoke($queue, new RecordsTenantJob, 'default');

    expect(json_decode($payload, true)[QueueTenancy::PAYLOAD_KEY])->toBe($organization->id);
});

test('a worker restores the tenant from the payload and clears it afterwards', function () {
    $organization = Organization::factory()->create();
    $tenancy = app(QueueTenancy::class);
    $current = app(CurrentOrganization::class);

    $tenancy->enter([QueueTenancy::PAYLOAD_KEY => $organization->id]);
    expect($current->id())->toBe($organization->id);

    $tenancy->leave();
    expect($current->check())->toBeFalse();
});
