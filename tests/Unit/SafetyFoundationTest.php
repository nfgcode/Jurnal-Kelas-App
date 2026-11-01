<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SafetyFoundationTest extends TestCase
{
    public function test_runner_isolates_hostile_environment_and_cleans_its_own_run(): void
    {
        $runner = dirname(__DIR__).'/run.php';
        $this->assertFileExists($runner, 'Runner must own PHP temp configuration and cleanup.');
        $report = tempnam(sys_get_temp_dir(), 'safety-report-');
        $env = array_merge(getenv(), [
            'APP_ENV' => 'production', 'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => 'primary', 'DB_URL' => 'mysql://primary/primary',
            'APP_CONFIG_CACHE' => dirname(__DIR__, 2).'/bootstrap/cache/config.php',
            'LARAVEL_STORAGE_PATH' => dirname(__DIR__, 2).'/storage',
            'EXPORT_YEAR_QA' => '1', 'EXPORT_TRANSITION_QA' => '1',
            'TEST_PROBE_REPORT' => $report,
        ]);
        $process = proc_open([PHP_BINARY, $runner, dirname(__DIR__).'/Fixtures/SafetyEnvironmentProbeTest.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $output);
        $data = json_decode(file_get_contents($report), true, flags: JSON_THROW_ON_ERROR);
        unlink($report);
        $this->assertDirectoryDoesNotExist($data['root']);
        $this->assertDirectoryExists(sys_get_temp_dir(), 'Child cleanup must not delete the parent sandbox.');
    }

    public function test_runner_cleans_after_child_crash(): void
    {
        $report = tempnam(sys_get_temp_dir(), 'crash-report-');
        $env = array_merge(getenv(), ['TEST_PROBE_REPORT' => $report, 'TEST_PROBE_CRASH' => '1']);
        $process = proc_open([PHP_BINARY, dirname(__DIR__).'/run.php', dirname(__DIR__).'/Fixtures/SafetyEnvironmentProbeTest.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertNotSame(0, proc_close($process), $output);
        $this->assertStringContainsString('Premature end of PHP process', $output);
        $data = json_decode(file_get_contents($report), true, flags: JSON_THROW_ON_ERROR);
        unlink($report);
        $this->assertDirectoryDoesNotExist($data['root']);
    }

    public function test_mysql_integration_is_rejected_before_autoload(): void
    {
        $env = array_merge(getenv(), ['APP_ENV' => 'production', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'live_jurusan_test_trap', 'DB_URL' => 'mysql://primary/live']);
        $script = dirname(__DIR__).'/Integration/jurusan_mysql.php';
        $this->assertStringContainsString("require __DIR__.'/mysql_guard.php'", file_get_contents($script));
        $process = proc_open([PHP_BINARY, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertNotSame(0, proc_close($process));
        $this->assertStringContainsString('Unsafe MySQL integration', $output);
    }

    public function test_bootstrap_refuses_unowned_execution_before_autoload(): void
    {
        $bootstrap = dirname(__DIR__).'/bootstrap.php';
        $this->assertFileExists($bootstrap, 'A fail-closed bootstrap must exist before Laravel can load.');
        $env = getenv();
        unset($env['TEST_RUN_ROOT'], $env['TEST_RUN_TOKEN']);
        $process = proc_open([PHP_BINARY, '-r', 'require '.var_export($bootstrap, true).';'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertNotSame(0, proc_close($process));
        $this->assertStringContainsString('Unsafe test execution', $output);
    }
}
