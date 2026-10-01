<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Classes seeded after 2026_07_30 came out without a QR token: DatabaseSeeder
 * runs WithoutModelEvents, so Kelas::booted()'s creating hook never fired, and
 * every route('qr.show', null) on the QR print page threw a 500. Give each such
 * class the token it should have had.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('kelas')->whereNull('qr_token')->pluck('id') as $id) {
            DB::table('kelas')->where('id', $id)->update(['qr_token' => (string) Str::uuid()]);
        }
    }

    public function down(): void
    {
        // Tokens are printed on classroom walls; never take them back.
    }
};
