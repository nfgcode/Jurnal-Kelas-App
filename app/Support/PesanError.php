<?php

namespace App\Support;

/**
 * Human wording for an HTTP error status, in Indonesian, for the friendly error
 * page guru/siswa see instead of a Laravel stack trace.
 */
class PesanError
{
    /**
     * @return array{judul: string, pesan: string, ikon: string, laporkan: bool}
     */
    public static function untuk(int $status): array
    {
        return match ($status) {
            403 => [
                'judul' => 'Akses ditolak',
                'pesan' => 'Anda tidak memiliki izin untuk membuka halaman ini. Bila Anda merasa seharusnya bisa, hubungi admin sekolah.',
                'ikon' => 'bi-shield-lock',
                // Nothing is broken here — a report would only be noise.
                'laporkan' => false,
            ],
            404 => [
                'judul' => 'Halaman tidak ditemukan',
                'pesan' => 'Halaman yang Anda tuju tidak ada atau sudah dipindahkan. Coba kembali ke halaman sebelumnya.',
                'ikon' => 'bi-signpost-2',
                'laporkan' => false,
            ],
            419 => [
                'judul' => 'Sesi Anda kedaluwarsa',
                'pesan' => 'Halaman terbuka terlalu lama sehingga sesi berakhir. Muat ulang halaman lalu coba lagi — data yang sudah tersimpan tidak hilang.',
                'ikon' => 'bi-hourglass-bottom',
                'laporkan' => false,
            ],
            429 => [
                'judul' => 'Terlalu banyak permintaan',
                'pesan' => 'Anda melakukan terlalu banyak permintaan dalam waktu singkat. Tunggu sebentar, lalu coba lagi.',
                'ikon' => 'bi-hourglass-split',
                'laporkan' => false,
            ],
            503 => [
                'judul' => 'Sedang pemeliharaan',
                'pesan' => 'Aplikasi sedang dalam pemeliharaan singkat. Silakan coba beberapa menit lagi.',
                'ikon' => 'bi-tools',
                'laporkan' => false,
            ],
            default => [
                'judul' => 'Terjadi kesalahan',
                'pesan' => 'Ada gangguan teknis saat memproses permintaan Anda. Kesalahan ini sudah dicatat sistem. Anda bisa kembali ke halaman sebelumnya, atau kirim laporan agar admin dapat menindaklanjuti.',
                'ikon' => 'bi-exclamation-triangle',
                'laporkan' => true,
            ],
        };
    }
}
