<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatKelasSiswa extends Model
{
    use HasFactory;

    protected $table = 'riwayat_kelas_siswa';

    protected $fillable = [
        'siswa_nis',
        'kelas_id',
        'tahun_ajaran_kode',
        'tingkat',
        'status',
        'aktif',
        'tanggal_mulai',
        'tanggal_selesai',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (RiwayatKelasSiswa $riwayat) {
            if ($riwayat->aktif) {
                $duplikatAktif = static::where('siswa_nis', $riwayat->siswa_nis)
                    ->where('aktif', true)
                    ->when($riwayat->exists, fn ($q) => $q->where('id', '!=', $riwayat->id))
                    ->exists();

                if ($duplikatAktif) {
                    throw new \DomainException(
                        "Siswa {$riwayat->siswa_nis} sudah memiliki keanggotaan kelas yang sedang aktif. Tutup atau nonaktifkan keanggotaan sebelumnya terlebih dahulu."
                    );
                }
            }
        });
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }

    /**
     * Daftarkan siswa ke kelas baru.
     */
    public static function daftarkan(
        Siswa $siswa,
        Kelas $kelas,
        string $tahunAjaranKode,
        ?string $tanggalMulai = null,
        ?string $catatan = null,
        string $status = 'aktif',
    ): self {
        return self::create([
            'siswa_nis' => $siswa->nis,
            'kelas_id' => $kelas->id,
            'tahun_ajaran_kode' => $tahunAjaranKode,
            'tingkat' => $kelas->tingkat,
            'status' => $status,
            'aktif' => true,
            'tanggal_mulai' => $tanggalMulai,
            'catatan' => $catatan,
        ]);
    }

    /**
     * Mutasi / pindah kelas mid-year: tutup keanggotaan aktif sebelumnya dan buka keanggotaan baru.
     */
    public static function mutasiKelas(
        Siswa $siswa,
        Kelas $kelasBaru,
        string $tahunAjaranKode,
        ?string $catatan = null,
        ?string $tanggal = null,
    ): self {
        $tanggal ??= now()->toDateString();

        // 1. Tutup keanggotaan aktif sebelumnya jika ada
        self::where('siswa_nis', $siswa->nis)
            ->where('aktif', true)
            ->update([
                'aktif' => false,
                'status' => 'pindah',
                'tanggal_selesai' => $tanggal,
                'updated_at' => now(),
            ]);

        // 2. Buka keanggotaan baru
        return self::create([
            'siswa_nis' => $siswa->nis,
            'kelas_id' => $kelasBaru->id,
            'tahun_ajaran_kode' => $tahunAjaranKode,
            'tingkat' => $kelasBaru->tingkat,
            'status' => 'aktif',
            'aktif' => true,
            'tanggal_mulai' => $tanggal,
            'catatan' => $catatan,
        ]);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_nis', 'nis');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_kode', 'kode');
    }
}
