<?php

namespace App\Support\Tenancy;

use App\Models\Membership;
use App\Models\Organization;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;

/**
 * Carries the current organization into queued jobs (including queued
 * notifications, mail and listeners) and restores it while the job runs.
 *
 * A stack is used so that sync-queue jobs dispatched during a request don't
 * clobber the request's own tenant context.
 */
class QueueTenancy
{
    public const PAYLOAD_KEY = 'organization_id';

    /**
     * @var list<array{0: ?Organization, 1: ?Membership}>
     */
    protected array $stack = [];

    public static function register(Dispatcher $events): void
    {
        Queue::createPayloadUsing(fn () => [
            self::PAYLOAD_KEY => app(CurrentOrganization::class)->id(),
        ]);

        $events->listen(JobProcessing::class, fn (JobProcessing $event) => app(self::class)->enter($event->job->payload()));
        $events->listen(JobProcessed::class, fn () => app(self::class)->leave());
        $events->listen(JobExceptionOccurred::class, fn () => app(self::class)->leave());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function enter(array $payload): void
    {
        $current = app(CurrentOrganization::class);
        $this->stack[] = [$current->get(), $current->membership()];

        $organizationId = $payload[self::PAYLOAD_KEY] ?? null;
        $organization = is_int($organizationId) ? Organization::find($organizationId) : null;

        $organization !== null ? $current->set($organization) : $current->forget();
    }

    public function leave(): void
    {
        if ($this->stack === []) {
            return;
        }

        [$organization, $membership] = array_pop($this->stack);
        $current = app(CurrentOrganization::class);

        $organization !== null ? $current->set($organization, $membership) : $current->forget();
    }
}
