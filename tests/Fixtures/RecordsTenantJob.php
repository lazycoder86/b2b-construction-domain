<?php

namespace Tests\Fixtures;

use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordsTenantJob implements ShouldQueue
{
    use Queueable;

    /** @var list<int|null> */
    public static array $seen = [];

    public function handle(CurrentOrganization $current): void
    {
        static::$seen[] = $current->id();
    }
}
