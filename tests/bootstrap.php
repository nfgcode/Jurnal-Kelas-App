<?php

// This file runs before Composer, Dotenv, Laravel, providers, or any DB access.
$root = (string) getenv('TEST_RUN_ROOT');
$token = (string) getenv('TEST_RUN_TOKEN');
$realRoot = realpath($root);
$marker = $root.'/.test-owner';
if (! preg_match('/\A[a-f0-9]{32}\z/D', $token)
    || $realRoot === false || is_link($root)
    || basename($realRoot) !== 'jurnal-tests-'.$token
    || ! is_file($marker) || ! hash_equals($token, (string) file_get_contents($marker))
    || realpath(sys_get_temp_dir()) !== realpath($root.'/tmp')) {
    throw new RuntimeException('Unsafe test execution: use php tests/run.php (owned scratch + PHP sys_temp_dir required).');
}

// Laravel recognizes Windows absolute paths with forward slashes.
$realRoot = str_replace('\\', '/', $realRoot);
$settings = [
    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
    'DB_URL' => '', 'DATABASE_URL' => '', 'DB_HOST' => '', 'DB_PORT' => '',
    'DB_USERNAME' => '', 'DB_PASSWORD' => '',
    'LARAVEL_STORAGE_PATH' => $realRoot.'/storage',
    'APP_CONFIG_CACHE' => $realRoot.'/cache/config.php',
    'APP_SERVICES_CACHE' => $realRoot.'/cache/services.php',
    'APP_PACKAGES_CACHE' => $realRoot.'/cache/packages.php',
    'APP_EVENTS_CACHE' => $realRoot.'/cache/events.php',
    'APP_ROUTES_CACHE' => $realRoot.'/cache/routes.php',
    'VIEW_COMPILED_PATH' => $realRoot.'/storage/framework/views',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array', 'BROADCAST_CONNECTION' => '"null"', 'LOG_CHANNEL' => '"null"',
    'FILESYSTEM_DISK' => 'local', 'APP_MAINTENANCE_DRIVER' => 'file',
    'EXPORT_YEAR_QA' => '', 'EXPORT_TRANSITION_QA' => '',
    'TMPDIR' => $realRoot.'/tmp', 'TMP' => $realRoot.'/tmp', 'TEMP' => $realRoot.'/tmp',
];
foreach ($settings as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require_once __DIR__.'/../vendor/autoload.php';
