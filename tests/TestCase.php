<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        putenv('APP_ENV=testing');
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';

        putenv('DB_DATABASE=not_alone_test');
        $_ENV['DB_DATABASE'] = 'not_alone_test';
        $_SERVER['DB_DATABASE'] = 'not_alone_test';

        putenv('SESSION_DRIVER=array');
        $_ENV['SESSION_DRIVER'] = 'array';
        $_SERVER['SESSION_DRIVER'] = 'array';

        putenv('QUEUE_CONNECTION=sync');
        $_ENV['QUEUE_CONNECTION'] = 'sync';
        $_SERVER['QUEUE_CONNECTION'] = 'sync';

        $app = parent::createApplication();

        $app['config']->set('database.connections.pgsql.database', 'not_alone_test');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');

        return $app;
    }
}
