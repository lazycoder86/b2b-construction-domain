<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase wipes the database it runs against, so refuse to boot
     * tests unless the configured database is clearly a test database.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if ($database !== ':memory:' && ! str_ends_with($database, '_test')) {
            throw new RuntimeException("Refusing to run tests against database [{$database}]; its name must end in \"_test\".");
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Page tests assert Inertia props; they shouldn't depend on a Vite build.
        $this->withoutVite();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
