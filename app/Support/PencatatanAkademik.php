<?php

namespace App\Support;

use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\TahunAjaran;
use Illuminate\Support\Facades\DB;

/**
 * Adding a class, a subject or a timetable slot — through the database's own
 * procedures on MySQL, and through the identical sequence in PHP elsewhere.
 *
 * The procedures are where the rules actually live, so a bulk import or a
 * hand-written INSERT meets the same refusals the form does. What comes back
 * here is a short code; {@see ProsedurTersimpan} turns it into an error on the
 * field it belongs to.
 */
class PencatatanAkademik extends ProsedurTersimpan
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    protected function pesan(): array
    {
        return [
            'ROMBEL_SUDAH_ADA' => ['paralel', 'Rombel dengan tingkat, jurusan, dan nomor paralel ini sudah ada di tahun ajaran tersebut.'],
            'TAHUN_AJARAN_TIDAK_ADA' => ['tahun_ajaran_kode', 'Tahun ajaran ini belum terdaftar.'],
            'JURUSAN_TIDAK_ADA' => ['jurusan_kode', 'Jurusan ini belum terdaftar.'],
            'WALI_TIDAK_AKTIF' => ['wali_kelas_nip', 'Wali kelas harus guru yang berstatus aktif.'],

            'KODE_MAPEL_SUDAH_ADA' => ['kode', 'Kode mata pelajaran ini sudah dipakai.'],
            'GURU_TIDAK_DIKENAL' => ['guru_nip', 'Ada NIP guru yang tidak terdaftar.'],

            'JP_TERBALIK' => ['jam_ke_selesai', 'JP selesai tidak boleh lebih awal dari JP mulai.'],
            'GURU_TIDAK_MENGAMPU' => ['guru_nip', 'Guru ini tidak tercatat mengampu mata pelajaran tersebut.'],
            'KELAS_BENTROK' => ['kelas_id', 'Kelas ini sudah ada pelajaran lain pada jam tersebut.'],
            'GURU_BENTROK' => ['guru_nip', 'Guru ini sudah mengajar di jam tersebut.'],
            'RUANGAN_BENTROK' => ['ruangan_kode', 'Ruangan ini sudah dipakai pada jam tersebut.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function kelas(array $data): Kelas
    {
        $id = null;

        $this->jalankan(
            mysql: function () use ($data, &$id) {
                DB::statement('CALL sp_tambah_kelas(?,?,?,?,?,?,?,?, @kelas_id)', [
                    $data['nama_kelas'],
                    $data['tingkat'],
                    $data['jurusan_kode'] ?? null,
                    $data['paralel'] ?? 1,
                    $data['ruangan_kode'] ?? null,
                    $data['kapasitas'] ?? 36,
                    $data['tahun_ajaran_kode'],
                    $data['wali_kelas_nip'] ?? null,
                ]);
                $id = DB::selectOne('SELECT @kelas_id AS id')->id;
            },
            // A closure, not an arrow function: `fn ()` captures by value, so
            // the id assigned inside would never reach $id out here.
            portabel: function () use ($data, &$id) {
                $id = $this->kelasPortabel($data)->id;
            },
        );

        return Kelas::findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $guruNip  teachers certified for this subject
     */
    public function mataPelajaran(array $data, array $guruNip = []): MataPelajaran
    {
        $id = null;

        $this->jalankan(
            mysql: function () use ($data, $guruNip, &$id) {
                DB::statement('CALL sp_tambah_mata_pelajaran(?,?,?,?,?,?, @mapel_id)', [
                    $data['nama'],
                    $data['kode'],
                    $data['kelompok'] ?? 'wajib',
                    $data['jp_per_minggu'] ?? 2,
                    $data['deskripsi'] ?? null,
                    json_encode(array_values($guruNip)),
                ]);
                $id = DB::selectOne('SELECT @mapel_id AS id')->id;
            },
            portabel: function () use ($data, $guruNip, &$id) {
                $id = $this->mataPelajaranPortabel($data, $guruNip)->id;
            },
        );

        return MataPelajaran::findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function jadwal(array $data): Jadwal
    {
        $id = null;

        $this->jalankan(
            mysql: function () use ($data, &$id) {
                DB::statement('CALL sp_tambah_jadwal(?,?,?,?,?,?,?, @jadwal_id)', [
                    $data['kelas_id'],
                    $data['mata_pelajaran_id'],
                    $data['guru_nip'],
                    $data['hari'],
                    $data['jam_ke_mulai'],
                    $data['jam_ke_selesai'],
                    $data['ruangan_kode'] ?? null,
                ]);
                $id = DB::selectOne('SELECT @jadwal_id AS id')->id;
            },
            portabel: function () use ($data, &$id) {
                $id = $this->jadwalPortabel($data)->id;
            },
        );

        return Jadwal::findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function kelasPortabel(array $data): Kelas
    {
        $this->tolakBila(
            ! TahunAjaran::whereKey($data['tahun_ajaran_kode'])->exists(),
            'TAHUN_AJARAN_TIDAK_ADA',
        );
        $this->tolakBila(
            ! empty($data['jurusan_kode']) && ! Jurusan::whereKey($data['jurusan_kode'])->exists(),
            'JURUSAN_TIDAK_ADA',
        );
        $this->tolakBila(
            ! empty($data['wali_kelas_nip'])
                && ! Guru::whereKey($data['wali_kelas_nip'])->where('status', 'aktif')->exists(),
            'WALI_TIDAK_AKTIF',
        );

        return DB::transaction(function () use ($data) {
            $this->tolakBila(
                Kelas::where('tahun_ajaran_kode', $data['tahun_ajaran_kode'])
                    ->where('tingkat', $data['tingkat'])
                    ->where('jurusan_kode', $data['jurusan_kode'] ?? null)
                    ->where('paralel', $data['paralel'] ?? 1)
                    ->exists(),
                'ROMBEL_SUDAH_ADA',
            );

            return Kelas::create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $guruNip
     */
    private function mataPelajaranPortabel(array $data, array $guruNip): MataPelajaran
    {
        $this->tolakBila(
            MataPelajaran::where('kode', trim((string) $data['kode']))->exists(),
            'KODE_MAPEL_SUDAH_ADA',
        );
        $this->tolakBila(
            $guruNip !== [] && Guru::whereIn('nip', $guruNip)->count() !== count(array_unique($guruNip)),
            'GURU_TIDAK_DIKENAL',
        );

        return DB::transaction(function () use ($data, $guruNip) {
            $mapel = MataPelajaran::create($data);

            if ($guruNip !== []) {
                $mapel->guru()->attach(array_unique($guruNip), ['utama' => false]);
            }

            return $mapel;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function jadwalPortabel(array $data): Jadwal
    {
        $this->tolakBila((int) $data['jam_ke_selesai'] < (int) $data['jam_ke_mulai'], 'JP_TERBALIK');
        $this->tolakBila(
            ! DB::table('guru_mata_pelajaran')
                ->where('guru_nip', $data['guru_nip'])
                ->where('mata_pelajaran_id', $data['mata_pelajaran_id'])
                ->exists(),
            'GURU_TIDAK_MENGAMPU',
        );

        return DB::transaction(function () use ($data) {
            // Overlap, not equality: JP 10-11 and JP 11-12 share a period
            // without sharing a start.
            $bentrok = fn (string $kolom, mixed $nilai) => Jadwal::where($kolom, $nilai)
                ->where('hari', $data['hari'])
                ->where('jam_ke_mulai', '<=', (int) $data['jam_ke_selesai'])
                ->where('jam_ke_selesai', '>=', (int) $data['jam_ke_mulai'])
                ->exists();

            $this->tolakBila($bentrok('kelas_id', $data['kelas_id']), 'KELAS_BENTROK');
            $this->tolakBila($bentrok('guru_nip', $data['guru_nip']), 'GURU_BENTROK');
            $this->tolakBila(
                ! empty($data['ruangan_kode']) && $bentrok('ruangan_kode', $data['ruangan_kode']),
                'RUANGAN_BENTROK',
            );

            return Jadwal::create($data);
        });
    }
}
