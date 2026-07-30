<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stops duplicate journals for the same meeting.
 *
 * A meeting (jadwal + tanggal) may hold at most two journals: the guru's own, and
 * the one a ketua kelas files on their behalf. Anything beyond that is a
 * double-submit — it inflates journal-completeness and duplicates the record of a
 * single lesson. `diisi_oleh_peran` captures which side authored the row so the
 * database itself can enforce "one per side" instead of trusting the form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurnal', function (Blueprint $table) {
            $table->string('diisi_oleh_peran', 10)->nullable()->after('diisi_oleh_id');
        });

        // Backfill: the author's role decides the side. Rows with no recorded
        // author (older seeded data) belong to the guru who owns them. Written as
        // two portable statements — an UPDATE ... JOIN would be MySQL-only and the
        // test database is SQLite.
        DB::table('jurnal')->update(['diisi_oleh_peran' => 'guru']);

        DB::table('jurnal')
            ->whereIn('diisi_oleh_id', fn ($q) => $q->select('id')->from('users')->where('role', 'siswa'))
            ->update(['diisi_oleh_peran' => 'siswa']);

        $this->hapusDuplikat();

        Schema::table('jurnal', function (Blueprint $table) {
            $table->unique(['jadwal_id', 'tanggal', 'diisi_oleh_peran'], 'jurnal_pertemuan_peran_unique');
        });
    }

    public function down(): void
    {
        Schema::table('jurnal', function (Blueprint $table) {
            $table->dropUnique('jurnal_pertemuan_peran_unique');
            $table->dropColumn('diisi_oleh_peran');
        });
    }

    /**
     * Collapse pre-existing duplicates so the unique index can be created.
     *
     * Of each duplicate group the row worth keeping is the one carrying the most
     * attendance rows — deleting it would cascade that roster away — with the
     * newest row winning a tie, since a re-submit is usually the corrected one.
     */
    private function hapusDuplikat(): void
    {
        $grup = DB::table('jurnal')
            ->select('jadwal_id', 'tanggal', 'diisi_oleh_peran')
            ->groupBy('jadwal_id', 'tanggal', 'diisi_oleh_peran')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($grup as $g) {
            $baris = DB::table('jurnal')
                ->leftJoin('presensi', 'presensi.jurnal_id', '=', 'jurnal.id')
                ->where('jurnal.jadwal_id', $g->jadwal_id)
                ->where('jurnal.tanggal', $g->tanggal)
                ->where('jurnal.diisi_oleh_peran', $g->diisi_oleh_peran)
                ->groupBy('jurnal.id')
                ->orderByRaw('COUNT(presensi.id) DESC')
                ->orderByDesc('jurnal.id')
                ->pluck('jurnal.id');

            // Keep the first (richest) and drop the rest.
            $buang = $baris->skip(1)->all();

            if ($buang !== []) {
                DB::table('jurnal')->whereIn('id', $buang)->delete();
            }
        }
    }
};
