<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        // Las pruebas siempre corren como "testing", aunque en la computadora exista APP_ENV=local
        // (variable del sistema) o una configuración guardada con "php artisan config:cache".
        // Si no, Laravel exige el token CSRF y /auth/login responde 419 en vez de 422.
        $app->instance('env', 'testing');
        $app['config']->set('app.env', 'testing');

        // Las pruebas siempre usan una base SQLite temporal, nunca la base real.
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.sqlite.url', null);
        $app['config']->set('database.connections.sqlite.foreign_key_constraints', true);

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        return $app;
    }
}
