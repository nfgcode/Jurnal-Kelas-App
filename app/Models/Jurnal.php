<?php

namespace App\Models;

use App\Support\DbDriver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jurnal extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'jurnal';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'jadwal_id',
        'tanggal',
        'materi',
        'tugas',
        'kegiatan',
        'catatan',
        'kehadiran_guru_status',
        'kehadiran_guru_alasan',
        'kehadiran_guru_ada_tugas',
        'kehadiran_guru_keterangan',
        'guru_id',
        'diisi_oleh_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'kehadiran_guru_ada_tugas' => 'boolean',
        ];
    }

    /**
     * Journals belonging to one class, whoever taught the lesson — the base the
     * class dashboard, recap, and wali-kelas screens all draw from.
     */
    public function scopeUntukKelas($query, int $kelasId)
    {
        return $query->whereHas('jadwal', fn ($j) => $j->where('kelas_id', $kelasId));
    }

    /**
     * The human search every journal list shares: what was taught (materi,
     * tugas, kegiatan) and whose lesson it was (teacher, class, subject).
     */
    public function scopeCari($query, string $q)
    {
        return $query->where(fn ($inner) => $inner
            ->where('materi', 'like', "%{$q}%")
            ->orWhere('tugas', 'like', "%{$q}%")
            ->orWhere('kegiatan', 'like', "%{$q}%")
            ->orWhereHas('guru', fn ($g) => $g->where('name', 'like', "%{$q}%"))
            ->orWhereHas('jadwal.kelas', fn ($k) => $k->where('nama_kelas', 'like', "%{$q}%"))
            ->orWhereHas('jadwal.mataPelajaran', fn ($m) => $m->where('nama', 'like', "%{$q}%")));
    }

    /**
     * Natural-language full-text search over the journal body (the API's `q`),
     * backed by the ft_jurnal FULLTEXT index on MySQL with a LIKE fallback for
     * the SQLite test suite.
     */
    public function scopeCariTeks($query, string $q)
    {
        if (DbDriver::mysql()) {
            return $query->whereRaw('MATCH(materi, kegiatan) AGAINST (? IN NATURAL LANGUAGE MODE)', [$q]);
        }

        return $query->where(fn ($inner) => $inner
            ->where('materi', 'like', "%{$q}%")
            ->orWhere('kegiatan', 'like', "%{$q}%"));
    }

    /**
     * The merged teacher-attendance chip shown on every report screen.
     *
     * The guru and the ketua kelas describe the same absence differently — the
     * guru says whether work was left, the student says why the guru was away —
     * so the display vocabulary is the union of both, resolved here rather than
     * stored.
     *
     * @return array{label: string, tone: string}
     */
    public function kehadiranGuruChip(): array
    {
        if ($this->kehadiran_guru_status === 'hadir') {
            return ['label' => 'Hadir', 'tone' => 'green'];
        }

        // A student-reported reason is more specific than the teacher's
        // mitigation flag, so it wins when both are present.
        return match ($this->kehadiran_guru_alasan) {
            'sakit' => ['label' => 'Sakit', 'tone' => 'khaki'],
            'izin' => ['label' => 'Izin', 'tone' => 'yellow'],
            'alpa' => ['label' => 'Alpa', 'tone' => 'red'],
            default => $this->kehadiran_guru_ada_tugas
                ? ['label' => 'Ada Tugas', 'tone' => 'yellow']
                : ['label' => 'Tanpa Tugas', 'tone' => 'red'],
        };
    }

    /**
     * Journal fill status as the screens label it: a journal written more than
     * a day after the lesson counts as late.
     *
     * @return array{label: string, tone: string}
     */
    public function statusPengisian(): array
    {
        if (blank($this->materi)) {
            return ['label' => 'Belum', 'tone' => 'neutral'];
        }

        $terlambat = $this->created_at
            && $this->created_at->greaterThan($this->tanggal->copy()->endOfDay()->addDay());

        return $terlambat
            ? ['label' => 'Telat', 'tone' => 'yellow']
            : ['label' => 'Terisi', 'tone' => 'green'];
    }

    /**
     * A SQL predicate matching late journals — filed more than a day after the
     * lesson they describe. Date arithmetic has no portable syntax, so the
     * expression is chosen per driver (MySQL in production, SQLite in tests).
     * Shared by the report and the dashboard drill-down.
     */
    public static function ekspresiTerlambat(): string
    {
        return \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite'
            ? "jurnal.created_at > datetime(jurnal.tanggal, '+2 day')"
            : 'jurnal.created_at > DATE_ADD(jurnal.tanggal, INTERVAL 2 DAY)';
    }

    /**
     * The user who actually wrote the entry — the ketua kelas when the guru
     * delegated it, otherwise the guru themselves.
     */
    public function diisiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diisi_oleh_id');
    }

    /**
     * Get the jadwal (schedule) for this journal entry.
     */
    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class);
    }

    /**
     * Get the guru (teacher) who created this journal entry.
     */
    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    /**
     * Get the presensi (attendance) records for this journal entry.
     */
    public function presensis(): HasMany
    {
        return $this->hasMany(Presensi::class);
    }
}
