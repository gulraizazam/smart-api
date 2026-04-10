<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // Many models in this codebase use plural names (Locations, Services,
        // Patients, ...) while their factories use the singular convention
        // (LocationFactory, ServiceFactory, ...). Laravel's auto-resolver
        // only matches the exact `{ModelName}Factory` form, so override the
        // resolver to fall back to the singularised name when the plural
        // factory class doesn't exist.
        Factory::guessFactoryNamesUsing(static function (string $modelName): string {
            $base = class_basename($modelName);
            $pluralFactory = 'Database\\Factories\\'.$base.'Factory';

            if (class_exists($pluralFactory)) {
                return $pluralFactory;
            }

            $singularFactory = 'Database\\Factories\\'.Str::singular($base).'Factory';

            return $singularFactory;
        });

        return $app;
    }
}
