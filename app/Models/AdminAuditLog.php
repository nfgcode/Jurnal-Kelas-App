<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAuditLog extends Model
{
    use HasFactory;

    protected $table = 'admin_audit_log';

    public const UPDATED_AT = null;

    /**
     * Kata kunci sensitif yang wajib dihilangkan saat metadata dibaca untuk antarmuka.
     */
    public const KATA_SENSITIF_TAMPILAN = [
        'password',
        'password_confirmation',
        'remember_token',
        'token',
        'secret',
        'pin',
        'kartu',
        'catatan',
        'credential',
        'session',
        'api_key',
        'cookie',
        'bearer',
        'private',
        'auth',
        'hash',
    ];

    protected $fillable = [
        'user_id',
        'nama_user',
        'role_user',
        'aksi',
        'tipe_entitas',
        'entitas_id',
        'status',
        'deskripsi',
        'jumlah_data',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'jumlah_data' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Dapatkan metadata yang telah disanitasi secara rekursif untuk penayangan aman di antarmuka.
     *
     * @return array<string, mixed>|null
     */
    public function metadataSanitasi(): ?array
    {
        return self::filterSensitifRekursif($this->metadata);
    }

    /**
     * Accessor atribut metadata_sanitasi.
     *
     * @return array<string, mixed>|null
     */
    public function getMetadataSanitasiAttribute(): ?array
    {
        return $this->metadataSanitasi();
    }

    /**
     * Bersihkan array metadata secara rekursif dari sembarang kunci yang berpotensi sensitif.
     */
    private static function filterSensitifRekursif(?array $data): ?array
    {
        if ($data === null || $data === []) {
            return null;
        }

        $hasil = [];
        foreach ($data as $k => $v) {
            $kunciTeks = strtolower((string) $k);
            $sensitif = false;
            foreach (self::KATA_SENSITIF_TAMPILAN as $terlarang) {
                if (str_contains($kunciTeks, $terlarang)) {
                    $sensitif = true;
                    break;
                }
            }

            if ($sensitif) {
                continue; // Atribut sensitif dibuang dari tampilan
            }

            if (is_array($v)) {
                $sub = self::filterSensitifRekursif($v);
                if ($sub !== null && $sub !== []) {
                    $hasil[$k] = $sub;
                }
            } else {
                $hasil[$k] = $v;
            }
        }

        return $hasil === [] ? null : $hasil;
    }
}
