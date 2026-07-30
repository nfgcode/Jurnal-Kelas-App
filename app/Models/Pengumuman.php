<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * An in-app banner an admin posts for guru/siswa (maintenance window, known
 * disruption, general notice).
 */
class Pengumuman extends Model
{
    protected $table = 'pengumuman';

    /** Banner flavours, mapped to a tone by {@see chip()}. */
    public const TIPE = ['info', 'peringatan', 'maintenance'];

    protected $fillable = [
        'pesan',
        'tipe',
        'aktif',
        'mulai',
        'selesai',
        'dibuat_oleh_id',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'mulai' => 'datetime',
            'selesai' => 'datetime',
        ];
    }

    /** Cache key for the banner's on-screen set. */
    private const CACHE_KUNCI = 'pengumuman.tayang';

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh_id');
    }

    /**
     * The banners to render, cached because this is read on **every** guru/siswa
     * page load. Kept short (60s) and invalidated explicitly by
     * {@see lupakanTayang()} whenever an admin posts or switches one, so a notice
     * appears immediately rather than after the TTL.
     *
     * Plain arrays, never Eloquent models: a serialized model round-tripped
     * through Redis can come back as __PHP_Incomplete_Class, which the array
     * cache driver used by the test suite would never reveal.
     *
     * @return array<int, array{pesan: string, ikon: string, warna: string}>
     */
    public static function untukBanner(): array
    {
        return Cache::remember(self::CACHE_KUNCI, 60, fn () => self::sedangTayang()
            ->latest('id')
            ->get()
            ->map(fn (self $p) => ['pesan' => $p->pesan] + $p->tampilan())
            ->all());
    }

    /** Drop the cached banner set after any change. */
    public static function lupakanTayang(): void
    {
        Cache::forget(self::CACHE_KUNCI);
    }

    /**
     * Announcements that should be on screen right now: switched on, and inside
     * their window when one is set (a null bound means open-ended).
     */
    public function scopeSedangTayang($query)
    {
        return $query->where('aktif', true)
            ->where(fn ($q) => $q->whereNull('mulai')->orWhere('mulai', '<=', now()))
            ->where(fn ($q) => $q->whereNull('selesai')->orWhere('selesai', '>=', now()));
    }

    /**
     * Presentation for the banner.
     *
     * @return array{ikon: string, warna: string}
     */
    public function tampilan(): array
    {
        return match ($this->tipe) {
            'maintenance' => ['ikon' => 'bi-tools', 'warna' => 'maintenance'],
            'peringatan' => ['ikon' => 'bi-exclamation-triangle', 'warna' => 'peringatan'],
            default => ['ikon' => 'bi-info-circle', 'warna' => 'info'],
        };
    }
}
