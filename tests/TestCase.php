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

        putenv('CACHE_STORE=array');
        $_ENV['CACHE_STORE'] = 'array';
        $_SERVER['CACHE_STORE'] = 'array';

        putenv('REDIS_DB=15');
        $_ENV['REDIS_DB'] = '15';
        $_SERVER['REDIS_DB'] = '15';

        putenv('REDIS_CACHE_DB=15');
        $_ENV['REDIS_CACHE_DB'] = '15';
        $_SERVER['REDIS_CACHE_DB'] = '15';

        putenv('REDIS_PREFIX=test_');
        $_ENV['REDIS_PREFIX'] = 'test_';
        $_SERVER['REDIS_PREFIX'] = 'test_';

        putenv('FILESYSTEM_DISK=local');
        $_ENV['FILESYSTEM_DISK'] = 'local';
        $_SERVER['FILESYSTEM_DISK'] = 'local';

        putenv('BROADCAST_CONNECTION=null');
        $_ENV['BROADCAST_CONNECTION'] = 'null';
        $_SERVER['BROADCAST_CONNECTION'] = 'null';

        putenv('MAIL_MAILER=array');
        $_ENV['MAIL_MAILER'] = 'array';
        $_SERVER['MAIL_MAILER'] = 'array';

        $app = parent::createApplication();

        $app['config']->set('database.connections.pgsql.database', 'not_alone_test');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.redis.default.database', 15);
        $app['config']->set('database.redis.cache.database', 15);
        $app['config']->set('database.redis.options.prefix', 'test_');
        $app['config']->set('filesystems.default', 'local');
        $app['config']->set('broadcasting.default', 'null');
        $app['config']->set('mail.default', 'array');

        return $app;
    }
}
