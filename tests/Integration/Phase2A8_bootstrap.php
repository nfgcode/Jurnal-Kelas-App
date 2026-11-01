<?php
// Only the dedicated Docker runner supplies these random, ephemeral values.
$token = getenv('PHASE2A8_TOKEN');
$password = getenv('PHASE2A8_PASSWORD');
$database = getenv('PHASE2A8_DATABASE');
if (! preg_match('/\A[a-f0-9]{32}\z/D', (string) $token)
    || ! preg_match('/\A[a-f0-9]{64}\z/D', (string) $password)
    || $database !== 'phase2a8_'.$token || ! is_dir('/scratch') || ! is_file('/.dockerenv')) {
    throw new RuntimeException('Dedicated disposable runner required.');
}
$pdo = new PDO('mysql:host=disposable-db;port=3306;dbname='.$database, 'root', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$identity = $pdo->query('SELECT @@server_uuid server_uuid, @@version version, DATABASE() `database`, @@hostname hostname')->fetch(PDO::FETCH_ASSOC);
if (($argv[1] ?? '') === 'probe') { echo json_encode($identity); exit; }
$expected = '/scratch/server-uuid';
if (is_file($expected)) {
    if (! hash_equals(trim(file_get_contents($expected)), $identity['server_uuid'])) { throw new RuntimeException('Server identity changed.'); }
} else { file_put_contents($expected, $identity['server_uuid']); }
foreach (['tmp','cache','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs','storage/app/private'] as $dir) {
    if (! is_dir('/scratch/'.$dir)) { mkdir('/scratch/'.$dir, 0700, true); }
}
$settings = [
    'APP_ENV'=>'testing', 'APP_DEBUG'=>'false', 'APP_KEY'=>'base64:'.base64_encode(str_repeat('x',32)),
    'DB_CONNECTION'=>'mysql', 'DB_HOST'=>'disposable-db','DB_PORT'=>'3306','DB_DATABASE'=>$database,'DB_USERNAME'=>'root','DB_PASSWORD'=>$password,
    'DB_URL'=>'','DATABASE_URL'=>'','LARAVEL_STORAGE_PATH'=>'/scratch/storage',
    'APP_CONFIG_CACHE'=>'/scratch/cache/config.php','APP_SERVICES_CACHE'=>'/scratch/cache/services.php','APP_PACKAGES_CACHE'=>'/scratch/cache/packages.php',
    'APP_EVENTS_CACHE'=>'/scratch/cache/events.php','APP_ROUTES_CACHE'=>'/scratch/cache/routes.php','VIEW_COMPILED_PATH'=>'/scratch/storage/framework/views',
    'CACHE_STORE'=>'array','SESSION_DRIVER'=>'array','QUEUE_CONNECTION'=>'sync','MAIL_MAILER'=>'array','BROADCAST_CONNECTION'=>'null','LOG_CHANNEL'=>'null',
    'FILESYSTEM_DISK'=>'local','EXPORT_YEAR_QA'=>'','EXPORT_TRANSITION_QA'=>'','TMPDIR'=>'/scratch/tmp',
];
foreach ($settings as $key=>$value) { putenv($key.'='.$value); $_ENV[$key]=$_SERVER[$key]=$value; }
require '/repo/vendor/autoload.php';
$app = require '/repo/bootstrap/app.php';
$app->useEnvironmentPath('/scratch');
$app->loadEnvironmentFrom('nonexistent.env');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\DB::purge();
$config = config('database.connections.mysql');
if ($config['host'] !== 'disposable-db' || $config['database'] !== $database || $config['url']) { throw new RuntimeException('Unexpected effective database.'); }
