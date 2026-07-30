<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One error report from a guru/siswa. Read and triaged by admin on the
 * Sistem & Log page; `jumlah` counts how many times the same fault recurred.
 */
class LaporanError extends Model
{
    protected $table = 'laporan_error';

    /** The triage states an admin moves a report through. */
    public const STATUS = ['baru', 'diproses', 'selesai'];

    protected $fillable = [
        'user_id',
        'ref',
        'tanda_tangan',
        'status',
        'pesan',
        'url',
        'http_status',
        'exception_pesan',
        'exception_file',
        'exception_line',
        'jumlah',
    ];

    public function pelapor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Reports still needing attention. */
    public function scopeBelumSelesai($query)
    {
        return $query->where('status', '!=', 'selesai');
    }

    /**
     * The chip the inbox renders for this report's state.
     *
     * @return array{label: string, tone: string}
     */
    public function statusChip(): array
    {
        return match ($this->status) {
            'selesai' => ['label' => 'Selesai', 'tone' => 'green'],
            'diproses' => ['label' => 'Diproses', 'tone' => 'yellow'],
            default => ['label' => 'Baru', 'tone' => 'red'],
        };
    }
}
