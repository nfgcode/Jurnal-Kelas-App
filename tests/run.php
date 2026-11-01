<?php

// No application/autoload here: the parent owns one scratch tree, even if PHPUnit fatals.
$repo = dirname(__DIR__);
$args = array_slice($argv, 1);
for ($i = 0; $i < count($args); $i++) {
    if (in_array($args[$i], ['--filter', '--testsuite'], true)) {
        if (! isset($args[++$i])) {
            throw new RuntimeException('Missing PHPUnit option value.');
        }
    } elseif (! in_array($args[$i], ['--testdox', '--stop-on-failure', '--display-warnings', '--display-deprecations'], true)) {
        $path = realpath($args[$i]);
        $tests = realpath(__DIR__);
        if ($path === false || ! str_starts_with($path, $tests.DIRECTORY_SEPARATOR)
            || preg_match('~[/\\\\](Integration|Browser)[/\\\\]~', $path)) {
            throw new RuntimeException('Unsafe PHPUnit argument: '.$args[$i]);
        }
    }
}
$scratch = getenv('TMPDIR');
if (! $scratch && DIRECTORY_SEPARATOR === '\\') {
    $scratch = getenv('LOCALAPPDATA').'/hermes/cache/scratch';
}
$base = realpath($scratch ?: sys_get_temp_dir());
if ($base === false || str_starts_with(strtolower($base), strtolower(realpath($repo)))) {
    throw new RuntimeException('Scratch must be outside the application repository.');
}
$token = bin2hex(random_bytes(16));
$root = $base.'/jurnal-tests-'.$token;
if (! mkdir($root, 0700)) {
    throw new RuntimeException('Cannot create owned test scratch.');
}
file_put_contents($root.'/.test-owner', $token);
$remove = static function (string $path) use (&$remove): void {
    if (is_link($path) || ! is_dir($path)) {
        unlink($path);

        return;
    }
    foreach (new DirectoryIterator($path) as $entry) {
        if (! $entry->isDot()) {
            $remove($entry->getPathname());
        }
    }
    rmdir($path);
};
register_shutdown_function(static function () use ($root, $token, $remove): void {
    if (! is_link($root) && is_file($root.'/.test-owner')
        && hash_equals($token, (string) file_get_contents($root.'/.test-owner'))) {
        $remove($root);
    }
});
foreach (['tmp', 'cache', 'storage/app/private', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $dir) {
    if (! mkdir($root.'/'.$dir, 0700, true) && ! is_dir($root.'/'.$dir)) {
        throw new RuntimeException('Cannot initialize test scratch.');
    }
}
$env = array_merge(getenv(), ['TEST_RUN_ROOT' => $root, 'TEST_RUN_TOKEN' => $token]);
$command = [PHP_BINARY, '-d', 'sys_temp_dir='.$root.'/tmp', $repo.'/vendor/bin/phpunit',
    '--configuration', $repo.'/phpunit.xml', '--do-not-cache-result', ...$args];
$process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $repo, $env);
if (! is_resource($process)) {
    throw new RuntimeException('Cannot launch PHPUnit.');
}
exit(proc_close($process));
