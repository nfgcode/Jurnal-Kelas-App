<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

if (getenv('DB_CONNECTION') !== 'sqlite' || ! str_ends_with((string) getenv('DB_DATABASE'), 'jurusan-ui-20261007.sqlite')) {
    throw new RuntimeException('Use the disposable jurusan UI SQLite database.');
}

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if ($uri !== '/' && is_file(__DIR__.'/../../public'.$uri)) {
    return false;
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'sqlite' || ! str_ends_with(DB::connection()->getDatabaseName(), 'jurusan-ui-20261007.sqlite')) {
    throw new RuntimeException('The bootstrapped app must use the disposable jurusan UI database.');
}
$app->make(Vite::class)->useHotFile(__DIR__.'/jurusan-ui-no-hot');
$app->handleRequest(Request::capture());
