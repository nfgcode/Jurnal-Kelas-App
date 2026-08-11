<?php

namespace App\Models;

use App\Support\DbDriver;
use App\Support\Urutan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Jurnal extends Model
{
    use HasFactory;

    /**
     * The `diisi_oleh_peran` value the nightly backfill writes for a meeting that
     * ended with no journal: a system-generated placeholder marking the guru
     * absent (no assignment) so the record exists and its roster can be estimated.
     * A human editing it "adopts" the row, flipping the peran to guru/siswa.
     */
    public const PERAN_SISTEM = 'sistem';

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
        'public_id',
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
        'diedit_setelah_hari',
    ];

    /**
     * Model events.
     */
    protected static function booted(): void
    {
        // Every journal gets an opaque public id, so web URLs never carry the
        // sequential primary key.
        static::creating(function (self $jurnal) {
            $jurnal->public_id ??= (string) Str::ulid();
        });

        // Any edit landing on a later calendar day than the lesson is flagged for
        // the recap — distinct from "Telat" (filled late), and set here (not in a
        // controller) so every path, web or API, marks it the same way. Never
        // reset once true; a fresh backfill INSERT is a create, so it is exempt.
        static::updating(function (self $jurnal) {
            if (! $jurnal->diedit_setelah_hari && self::diubahLewatHari($jurnal->tanggal)) {
                $jurnal->diedit_setelah_hari = true;
            }
        });
    }

    /**
     * Web routes address a journal by its opaque id, not its primary key. The
     * API keeps using the numeric id: that contract is documented and already
     * guarded by token auth plus the same policies.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Which side of a meeting a journal was written from. A meeting may hold one
     * journal per side — the guru's own, and the one a ketua kelas files on their
     * behalf — enforced by the unique index (jadwal_id, tanggal, diisi_oleh_peran).
     */
    public static function peranPengisi(User $user): string
    {
        return $user->isSiswa() ? 'siswa' : 'guru';
    }

    /** Whether this journal is an unedited nightly backfill placeholder. */
    public function dibuatSistem(): bool
    {
        return $this->diisi_oleh_peran === self::PERAN_SISTEM;
    }

    /**
     * Only journals a person actually wrote — the basis of every "how much has
     * been filled in" figure. A nightly backfill placeholder proves the opposite
     * (nobody filled it), so counting it would let the automation quietly inflate
     * completeness and hide the teachers who still owe a journal.
     *
     * Written as an explicit null-safe pair because `!= 'sistem'` alone drops
     * rows whose peran is NULL (SQL three-valued logic) — older rows and any
     * created without the column would silently vanish from the numbers.
     */
    public function scopeManusia($query)
    {
        return $query->where(fn ($q) => $q
            ->whereNull('jurnal.diisi_oleh_peran')
            ->orWhere('jurnal.diisi_oleh_peran', '!=', self::PERAN_SISTEM));
    }

    /** Whether this journal was edited on a day after the lesson it records. */
    public function dieditSetelahHari(): bool
    {
        return (bool) $this->diedit_setelah_hari;
    }

    /** True when now falls on a later calendar day than $tanggal (the lesson date). */
    public static function diubahLewatHari(mixed $tanggal): bool
    {
        return today()->gt(Carbon::parse($tanggal)->startOfDay());
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
        // The rejection message names the meeting, so its slot is loaded here.
        return self::with(['diisiOleh', 'jadwal.kelas', 'jadwal.mataPelajaran'])
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
            'diedit_setelah_hari' => 'boolean',
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
        // A system-generated backfill is not a real fill: keep it visually distinct
        // so the dashboard never counts it as a teacher who did their journal.
        if ($this->dibuatSistem()) {
            return ['label' => 'Otomatis', 'tone' => 'neutral'];
        }

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
     * The sort map every table of journals draws from: UI column name => how to
     * order by it. One definition so the journal history, the student's history,
     * the attendance list, the wali screens and the admin report can never drift
     * into ordering "kelas" three different ways.
     *
     * Ordering by a related name uses a correlated subquery rather than a join so
     * the caller's own joins, filters and withCount aggregates are untouched.
     * Screens take a subset with array_intersect_key; the key set is the
     * whitelist {@see Urutan} checks the URL against.
     *
     * @param  User|null  $siswa  enables the "my own attendance" column, which is
     *                            per-reader and therefore not a fixed expression
     * @return array<string, callable>
     */
    public static function petaUrutan(?User $siswa = null): array
    {
        $lewatJadwal = fn (string $tabel, string $kolom, string $fk) => Jadwal::select("{$tabel}.{$kolom}")
            ->join($tabel, "jadwal.{$fk}", '=', "{$tabel}.id")
            ->whereColumn('jadwal.id', 'jurnal.jadwal_id')
            ->limit(1);

        $kolomJadwal = fn (string $kolom) => Jadwal::select($kolom)
            ->whereColumn('jadwal.id', 'jurnal.jadwal_id')
            ->limit(1);

        return [
            // Table-qualified: the admin report joins other tables, where a bare
            // `id` would be ambiguous. id as tie-breaker, or same-day rows would
            // shuffle between pages.
            'tanggal' => fn ($q, $dir) => $q->orderBy('jurnal.tanggal', $dir)->orderBy('jurnal.id', $dir),
            'kelas' => fn ($q, $dir) => $q->orderBy($lewatJadwal('kelas', 'nama_kelas', 'kelas_id'), $dir),
            'mapel' => fn ($q, $dir) => $q->orderBy($lewatJadwal('mata_pelajaran', 'nama', 'mata_pelajaran_id'), $dir),
            'guru' => fn ($q, $dir) => $q->orderBy(
                User::select('name')->whereColumn('users.id', 'jurnal.guru_id')->limit(1), $dir
            ),
            // The lesson periods the meeting occupies, e.g. "JP 3 - 4".
            'jam' => fn ($q, $dir) => $q->orderBy($kolomJadwal('jam_ke_mulai'), $dir),
            // Aliases added by withCount() on the calling query.
            'hadir' => fn ($q, $dir) => $q->orderBy('hadir_count', $dir),
            'sakit' => fn ($q, $dir) => $q->orderBy('sakit_count', $dir),
            'izin' => fn ($q, $dir) => $q->orderBy('izin_count', $dir),
            'alpa' => fn ($q, $dir) => $q->orderBy('alpa_count', $dir),
            'siswa' => fn ($q, $dir) => $q->orderBy('total_siswa', $dir),
            // The percentage the bar shows, not the raw count — a meeting with
            // 20/20 present should outrank one with 25/40. NULLIF keeps a roster
            // that was never marked from dividing by zero.
            'persen' => fn ($q, $dir) => $q->orderByRaw("hadir_count / NULLIF(total_siswa, 0) {$dir}"),
            // Hadir / tidak hadir + tugas / tidak hadir tanpa tugas, in that order.
            'kehadiran_guru' => fn ($q, $dir) => $q
                ->orderBy('jurnal.kehadiran_guru_status', $dir)
                ->orderBy('jurnal.kehadiran_guru_ada_tugas', $dir),
            'materi' => fn ($q, $dir) => $q->orderBy('jurnal.materi', $dir),
            'tugas' => fn ($q, $dir) => $q->orderBy('jurnal.tugas', $dir),
            // "Tepat/Telat" is a SQL predicate, so the chip can be sorted on too.
            'status' => fn ($q, $dir) => $q->orderByRaw(self::ekspresiTerlambat()." {$dir}"),
        ] + ($siswa === null ? [] : [
            // How this particular student was marked at each meeting. Per-reader,
            // so it only exists when a student is the one looking.
            'presensi_saya' => fn ($q, $dir) => $q->orderBy(
                Presensi::select('status')
                    ->whereColumn('presensi.jurnal_id', 'jurnal.id')
                    ->where('presensi.siswa_id', $siswa->id)
                    ->limit(1),
                $dir
            ),
        ]);
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
