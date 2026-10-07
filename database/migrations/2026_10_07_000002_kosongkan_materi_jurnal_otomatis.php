<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Placeholder journals (the nightly backfill's, and the one a guru opens by
 * taking the roll first) used to carry invented materi text. They now carry an
 * empty materi that screens show as "belum diisi", so clear the old text.
 */
return new class extends Migration
{
    private const TEKS_LAMA = 'Diisi otomatis oleh sistem — mohon lengkapi bila perlu.';

    public function up(): void
    {
        DB::table('jurnal')->where('materi', self::TEKS_LAMA)->update(['materi' => '']);
    }

    public function down(): void
    {
        DB::table('jurnal')
            ->where('diisi_oleh_peran', 'sistem')
            ->where('materi', '')
            ->update(['materi' => self::TEKS_LAMA]);
    }
};
