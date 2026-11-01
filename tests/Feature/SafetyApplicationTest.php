<?php

namespace Tests\Feature;

use Tests\TestCase;

final class SafetyApplicationTest extends TestCase
{
    public function test_laravel_boot_is_isolated_without_opening_a_database(): void
    {
        $root = str_replace('\\', '/', realpath(getenv('TEST_RUN_ROOT')));
        $this->assertSame('testing', $this->app->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertEmpty(config('database.connections.sqlite.url'));
        $this->assertSame($root.'/storage', storage_path());
        $this->assertSame($root.'/cache/config.php', $this->app->getCachedConfigPath());
        $this->assertStringStartsWith($root.'/', config('view.compiled'));
        $this->assertSame([], $this->app['db']->getConnections(), 'Boot must not open any DB connection.');
        $this->assertSame('array', config('cache.default'));
        $this->assertSame('array', config('session.driver'));
        $this->assertSame('null', config('logging.default'));
    }

    public function test_base_testcase_enforces_preboot_guard_even_without_phpunit_bootstrap(): void
    {
        $source = file_get_contents(dirname(__DIR__).'/TestCase.php');
        $this->assertStringContainsString("require __DIR__.'/bootstrap.php'", $source);
    }
}
