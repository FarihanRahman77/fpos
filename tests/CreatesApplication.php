<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // Tests refresh the database. Refuse to run against anything but a *_testing database, so a cached
        // config or a missing phpunit setting can never wipe the real data.
        $database = (string) $app['config']->get('database.connections.' . $app['config']->get('database.default') . '.database');
        if (!str_ends_with($database, '_testing')) {
            throw new \RuntimeException("Tests must run on a *_testing database, not '{$database}'. Check phpunit.xml.");
        }

        return $app;
    }
}
