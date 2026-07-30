<?php

namespace App\Support;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * The health of the running system, in two grades:
 *
 *  - {@see lengkap()} — every check, uncached, for the admin Sistem & Log page.
 *  - {@see ringkas()} — two cheap checks, cached 5 minutes, used by the layout to
 *    decide whether guru/siswa should see a "sedang ada gangguan" banner. It must
 *    stay cheap and quiet: it runs on ordinary page renders.
 *
 * Each check returns: nama, status (ok|warn|gagal|n/a), nilai, detail.
 */
class SistemStatus
{
    /** How long the banner-facing summary is trusted before re-checking. */
    private const CACHE_DETIK = 300;

    /** The advanced MySQL objects the app depends on (see the DB layer migrations). */
    private const OBJEK_MYSQL = [
        'VIEW' => ['v_jurnal_lengkap', 'v_rekap_presensi_kelas'],
        'FUNCTION' => ['fn_persentase_kehadiran_siswa', 'fn_persentase_kehadiran_kelas'],
        'PROCEDURE' => ['sp_simpan_presensi'],
        'TRIGGER' => ['trg_jurnal_after_update', 'trg_jurnal_after_delete'],
    ];

    /**
     * Full report for the admin page.
     *
     * @return array<int, array{nama: string, status: string, nilai: string, detail: string}>
     */
    public static function lengkap(): array
    {
        return array_merge(
            [self::cekDatabase(), self::cekMigrasi(), self::cekCache(), self::cekStorage(), self::cekLog()],
            self::cekObjekDatabase(),
            self::cekKonfigurasi(),
        );
    }

    /**
     * Cheap cached summary for the user-facing banner.
     *
     * Fails quiet: if the checker itself blows up we report healthy rather than
     * alarm every guru and siswa over a bug in this class. The admin page runs the
     * checks uncached and will surface the real state.
     *
     * @return array{sehat: bool, masalah: array<int, string>}
     */
    public static function ringkas(): array
    {
        try {
            return Cache::remember('sistem.ringkas', self::CACHE_DETIK, function () {
                $masalah = [];

                foreach ([self::cekMigrasi(), self::cekCache()] as $cek) {
                    if ($cek['status'] === 'gagal') {
                        $masalah[] = $cek['nama'].': '.$cek['detail'];
                    }
                }

                return ['sehat' => $masalah === [], 'masalah' => $masalah];
            });
        } catch (Throwable) {
            return ['sehat' => true, 'masalah' => []];
        }
    }

    /** Forget the cached summary — called after an admin fixes something. */
    public static function lupakanRingkas(): void
    {
        Cache::forget('sistem.ringkas');
    }

    private static function cekDatabase(): array
    {
        try {
            $mulai = microtime(true);
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $ms = round((microtime(true) - $mulai) * 1000, 1);

            return self::baris('Database', $ms > 500 ? 'warn' : 'ok', $ms.' ms',
                'Koneksi '.DB::connection()->getDriverName().' berhasil.');
        } catch (Throwable $e) {
            return self::baris('Database', 'gagal', 'tidak terhubung', Str::limit($e->getMessage(), 160));
        }
    }

    /**
     * Migration files present on disk but never run — the exact failure that made
     * saving a roster 500 once (`presensi_log` did not exist yet).
     */
    private static function cekMigrasi(): array
    {
        try {
            $migrator = App::make('migrator');
            $berkas = array_keys($migrator->getMigrationFiles(database_path('migrations')));
            $sudah = $migrator->getRepository()->getRan();
            $tertunda = array_values(array_diff($berkas, $sudah));

            if ($tertunda === []) {
                return self::baris('Migrasi', 'ok', count($berkas).' migrasi', 'Semua migrasi sudah dijalankan.');
            }

            return self::baris('Migrasi', 'gagal', count($tertunda).' tertunda',
                'Belum dijalankan: '.implode(', ', array_slice($tertunda, 0, 3))
                .(count($tertunda) > 3 ? ' …' : '').' — jalankan: php artisan migrate');
        } catch (Throwable $e) {
            return self::baris('Migrasi', 'warn', 'tidak terbaca', Str::limit($e->getMessage(), 160));
        }
    }

    private static function cekCache(): array
    {
        try {
            $nilai = (string) Str::uuid();
            Cache::put('sistem.ping', $nilai, 10);
            $kembali = Cache::get('sistem.ping');

            if ($kembali !== $nilai) {
                return self::baris('Cache / Redis', 'gagal', config('cache.default'),
                    'Nilai yang ditulis tidak terbaca kembali.');
            }

            return self::baris('Cache / Redis', 'ok', config('cache.default'),
                'Tulis-baca cache berhasil. Session: '.config('session.driver').', queue: '.config('queue.default').'.');
        } catch (Throwable $e) {
            return self::baris('Cache / Redis', 'gagal', config('cache.default'), Str::limit($e->getMessage(), 160));
        }
    }

    private static function cekStorage(): array
    {
        $jalur = [
            'storage/logs' => storage_path('logs'),
            'storage/framework' => storage_path('framework'),
            'storage/app' => storage_path('app'),
        ];

        $gagal = [];
        foreach ($jalur as $label => $path) {
            if (! is_dir($path) || ! is_writable($path)) {
                $gagal[] = $label;
            }
        }

        return $gagal === []
            ? self::baris('Storage', 'ok', 'writable', 'Semua direktori storage dapat ditulis.')
            : self::baris('Storage', 'gagal', 'tidak writable', 'Tidak bisa ditulis: '.implode(', ', $gagal));
    }

    private static function cekLog(): array
    {
        $berkas = storage_path('logs/laravel.log');

        if (! is_file($berkas)) {
            return self::baris('Berkas Log', 'ok', 'kosong', 'Belum ada berkas log.');
        }

        $mb = round(filesize($berkas) / 1048576, 2);

        // A log this big slows the viewer and usually means errors are piling up.
        return self::baris('Berkas Log', $mb > 50 ? 'warn' : 'ok', $mb.' MB',
            $mb > 50
                ? 'Log sudah besar — pertimbangkan membersihkannya.'
                : 'Ukuran log wajar.');
    }

    /**
     * The MySQL-only views/functions/procedure/triggers the app branches on. On
     * SQLite (the test database) these do not exist by design.
     *
     * @return array<int, array{nama: string, status: string, nilai: string, detail: string}>
     */
    private static function cekObjekDatabase(): array
    {
        if (! DbDriver::mysql()) {
            return [self::baris('Objek DB Lanjutan', 'n/a', 'SQLite',
                'View/function/procedure/trigger hanya ada di MySQL; jalur fallback dipakai.')];
        }

        try {
            $skema = DB::getDatabaseName();

            $ada = [
                'VIEW' => DB::table('information_schema.VIEWS')->where('TABLE_SCHEMA', $skema)->pluck('TABLE_NAME')->all(),
                'FUNCTION' => DB::table('information_schema.ROUTINES')->where('ROUTINE_SCHEMA', $skema)
                    ->where('ROUTINE_TYPE', 'FUNCTION')->pluck('ROUTINE_NAME')->all(),
                'PROCEDURE' => DB::table('information_schema.ROUTINES')->where('ROUTINE_SCHEMA', $skema)
                    ->where('ROUTINE_TYPE', 'PROCEDURE')->pluck('ROUTINE_NAME')->all(),
                'TRIGGER' => DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', $skema)
                    ->pluck('TRIGGER_NAME')->all(),
            ];

            $hilang = [];
            foreach (self::OBJEK_MYSQL as $tipe => $nama) {
                foreach ($nama as $satu) {
                    if (! in_array($satu, $ada[$tipe], true)) {
                        $hilang[] = strtolower($tipe).' '.$satu;
                    }
                }
            }

            $total = array_sum(array_map('count', self::OBJEK_MYSQL));

            return [$hilang === []
                ? self::baris('Objek DB Lanjutan', 'ok', $total.' objek',
                    'Semua view, function, procedure, dan trigger tersedia.')
                : self::baris('Objek DB Lanjutan', 'gagal', count($hilang).' hilang',
                    'Tidak ditemukan: '.implode(', ', $hilang)), ];
        } catch (Throwable $e) {
            return [self::baris('Objek DB Lanjutan', 'warn', 'tidak terbaca', Str::limit($e->getMessage(), 160))];
        }
    }

    /**
     * Configuration that is easy to get wrong on a school deployment.
     *
     * @return array<int, array{nama: string, status: string, nilai: string, detail: string}>
     */
    private static function cekKonfigurasi(): array
    {
        $hasil = [];

        $debug = (bool) config('app.debug');
        $env = (string) config('app.env');
        $hasil[] = $debug && $env !== 'local'
            ? self::baris('APP_DEBUG', 'gagal', 'aktif di '.$env,
                'Debug aktif di luar lingkungan local — matikan (APP_DEBUG=false) agar detail internal tidak bocor.')
            : self::baris('APP_DEBUG', 'ok', $debug ? 'aktif (local)' : 'nonaktif',
                $debug
                    ? 'Wajar untuk pengembangan; guru/siswa tetap mendapat halaman ramah.'
                    : 'Detail error disembunyikan dari semua peran.');

        $url = (string) config('app.url');
        $lokal = Str::contains($url, ['localhost', '127.0.0.1']);
        $hasil[] = self::baris('APP_URL', $lokal ? 'warn' : 'ok', $url,
            $lokal
                ? 'Masih localhost — QR kelas tidak bisa dibuka dari HP. Set ke alamat LAN sekolah, mis. http://192.168.1.10:8888.'
                : 'Sudah memakai alamat yang dapat diakses perangkat lain.');

        $hasil[] = self::baris('Cache Konfigurasi',
            App::configurationIsCached() ? 'warn' : 'ok',
            App::configurationIsCached() ? 'ter-cache' : 'tidak',
            App::configurationIsCached()
                ? 'Config ter-cache: perubahan .env tidak terbaca sampai config:clear.'
                : 'Perubahan konfigurasi langsung terbaca (cocok untuk pengembangan).');

        return $hasil;
    }

    /** @return array{nama: string, status: string, nilai: string, detail: string} */
    private static function baris(string $nama, string $status, string $nilai, string $detail): array
    {
        return compact('nama', 'status', 'nilai', 'detail');
    }
}
