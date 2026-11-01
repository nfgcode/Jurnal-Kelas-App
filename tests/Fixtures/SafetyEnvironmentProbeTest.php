<?php

use PHPUnit\Framework\TestCase;

final class SafetyEnvironmentProbeTest extends TestCase
{
    public function test_environment_before_any_laravel_boot(): void
    {
        $root = str_replace('\\', '/', realpath(getenv('TEST_RUN_ROOT')));
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'DATABASE_URL' => '', 'EXPORT_YEAR_QA' => '', 'EXPORT_TRANSITION_QA' => ''] as $key => $value) {
            self::assertSame($value, (string) getenv($key));
            self::assertSame($value, $_ENV[$key]);
            self::assertSame($value, $_SERVER[$key]);
        }
        foreach (['APP_CONFIG_CACHE', 'APP_SERVICES_CACHE', 'APP_PACKAGES_CACHE', 'APP_EVENTS_CACHE', 'APP_ROUTES_CACHE', 'LARAVEL_STORAGE_PATH', 'VIEW_COMPILED_PATH'] as $key) {
            self::assertStringStartsWith($root.'/', getenv($key));
        }
        self::assertSame(realpath($root.'/tmp'), realpath(sys_get_temp_dir()));
        self::assertFalse(class_exists('Illuminate\\Foundation\\Application', false));
        file_put_contents(getenv('TEST_PROBE_REPORT'), json_encode(['root' => $root], JSON_THROW_ON_ERROR));
        if (getenv('TEST_PROBE_CRASH')) {
            // Abrupt process termination skips PHPUnit teardown; parent must clean.
            exit(73);
        }
    }
}
