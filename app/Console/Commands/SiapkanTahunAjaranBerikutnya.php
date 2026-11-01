<?php

namespace App\Console\Commands;

use App\Services\KalenderTahunAjaran;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class SiapkanTahunAjaranBerikutnya extends Command
{
    protected $signature = 'tahun-ajaran:siapkan-berikutnya';

    protected $description = 'Siapkan periode nonaktif; tidak mengaktifkan, menaikkan kelas, atau meluluskan siswa';

    public function handle(KalenderTahunAjaran $kalender): int
    {
        try {
            $tahun = $kalender->siapkanBerikutnya();
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }
        $this->info("Tahun ajaran {$tahun->kode} tersedia; status tidak diubah.");

        return self::SUCCESS;
    }
}
