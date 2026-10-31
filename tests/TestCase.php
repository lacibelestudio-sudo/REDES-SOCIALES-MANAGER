<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connectionName = $app['config']->get('database.default');
        $connection = $app['config']->get("database.connections.{$connectionName}", []);
        $databaseUrl = is_array($connection) ? ($connection['url'] ?? null) : null;

        if (
            ! $app->environment('testing')
            || $connectionName !== 'sqlite'
            || ! is_array($connection)
            || ($connection['database'] ?? null) !== ':memory:'
            || ($databaseUrl !== null && $databaseUrl !== '')
        ) {
            throw new LogicException(
                'Refusing to run tests unless the effective database is isolated SQLite :memory:.',
            );
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
