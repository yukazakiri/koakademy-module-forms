<?php

declare(strict_types=1);

namespace Modules\Forms\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Forms\Providers\FormsServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [FormsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('forms.uploads_disk', 'local');
    }
}
