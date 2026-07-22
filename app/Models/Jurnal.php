<?php

namespace App\Models;

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
