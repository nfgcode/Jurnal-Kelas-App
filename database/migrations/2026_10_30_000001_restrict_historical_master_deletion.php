<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const KEYS = [
        ['jadwal', 'kelas_id', 'kelas', 'id'],
        ['jadwal', 'mata_pelajaran_id', 'mata_pelajaran', 'id'],
        ['jadwal', 'guru_nip', 'guru', 'nip'],
        ['jurnal', 'jadwal_id', 'jadwal', 'id'],
        ['presensi_harian', 'kelas_id', 'kelas', 'id'],
        ['presensi_harian_log', 'kelas_id', 'kelas', 'id'],
    ];

    public function up(): void
    {
        $this->replace('restrict');
    }

    public function down(): void
    {
        $this->replace('cascade');
    }

    private function replace(string $action): void
    {
        foreach (self::KEYS as [$table, $column, $parent, $key]) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $parent, $key, $action) {
                $blueprint->dropForeign([$column]);
                $blueprint->foreign($column)->references($key)->on($parent)->onDelete($action);
            });
        }
    }
};
