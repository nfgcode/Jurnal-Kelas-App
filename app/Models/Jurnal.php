<?php

namespace App\Models;

use App\Support\DbDriver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

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
        'diisi_oleh_peran',
    ];

    /**
     * Which side of a meeting a journal was written from. A meeting may hold one
     * journal per side — the guru's own, and the one a ketua kelas files on their
     * behalf — enforced by the unique index (jadwal_id, tanggal, diisi_oleh_peran).
     */
    public static function peranPengisi(User $user): string
    {
        return $user->isSiswa() ? 'siswa' : 'guru';
    }

    /**
     * How many distinct *meetings* a journal query covers.
     *
     * A meeting may hold two journals (the guru's and the ketua's) but was taught
     * once, so counting rows would inflate every "jurnal terisi" figure and push
     * completeness above 100%. Written as a grouped subquery because
     * `COUNT(DISTINCT a, b)` is MySQL-only and the test database is SQLite.
     *
     * @param  Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function hitungPertemuan($query): int
    {
        $dasar = $query instanceof Builder
            ? $query->clone()->getQuery()
            : $query->clone();

        return DB::query()->fromSub(
            $dasar->select('jurnal.jadwal_id', 'jurnal.tanggal')->groupBy('jurnal.jadwal_id', 'jurnal.tanggal'),
            'pertemuan'
        )->count();
    }

    /**
     * Another journal for the same meeting that already holds the attendance
     * roster, if any.
     *
     * A meeting may carry two journals (the guru's and the ketua's), but the class
     * was only taught once — so the roster belongs to exactly one of them.
     * Allowing a second set would double every attendance figure derived from
     * presensi rows.
     */
    public function pemegangPresensi(): ?self
    {
        return self::where('jadwal_id', $this->jadwal_id)
            ->whereDate('tanggal', $this->tanggal)
            ->whereKeyNot($this->getKey())
            ->whereHas('presensis')
            ->first();
    }

    /** Whether a failed write was the unique index rejecting a duplicate. */
    public static function pelanggaranGanda(QueryException $e): bool
    {
        return $e->getCode() === '23000'
            || str_contains($e->getMessage(), 'jurnal_pertemuan_peran_unique');
    }

    /**
     * The journal already filed for this meeting from the given side, if any.
     * Used to reject a double submit with a helpful message rather than letting
     * the unique index surface as a 500.
     */
    public static function sudahAda(int $jadwalId, string $tanggal, string $peran, ?int $kecuali = null): ?self
    {
        return self::with('diisiOleh')
            ->where('jadwal_id', $jadwalId)
            // whereDate, not a plain where: MySQL stores `tanggal` as DATE, but on
            // SQLite (the test database) Laravel writes "Y-m-d H:i:s", so a plain
            // equality match silently finds nothing there. The index still seeks on
            // the jadwal_id prefix, and this lookup runs once per journal save.
            ->whereDate('tanggal', $tanggal)
            ->where('diisi_oleh_peran', $peran)
            ->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali))
            ->first();
    }

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
        return DB::connection()->getDriverName() === 'sqlite'
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
