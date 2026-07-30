{{--
    The error page guru/siswa see instead of a Laravel stack trace. Deliberately
    standalone with inline CSS: an error can happen while rendering the app layout
    or before assets resolve, so this page must not depend on either.

    $status  int         HTTP status
    $ref     string      reference code the user can quote to admin
    $teks    array       wording from App\Support\PesanError
--}}
@php
    // $pengguna is passed in by the exception renderer; the Auth facade is the
    // fallback for the conventional errors/{code} views, which render normally.
    $user = $pengguna ?? Auth::user();
    // A report is only offered for genuine faults (5xx), and only to a signed-in
    // user — the throttle is per account.
    $bisaLapor = ($teks['laporkan'] ?? false) && $user !== null;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $teks['judul'] }} · Jurnal Kelas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #eef1f4;
            color: #1d2117;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .kotak {
            width: 100%;
            max-width: 520px;
            background: #fff;
            border: 1px solid #dfe4ea;
            border-radius: 14px;
            box-shadow: 0 8px 28px rgba(0, 0, 0, .07);
            padding: 30px 26px;
            text-align: center;
        }
        .lambang {
            width: 58px; height: 58px;
            margin: 0 auto 16px;
            border-radius: 16px;
            background: #23371d;
            color: #fff;
            display: grid; place-items: center;
            font-size: 27px;
        }
        .kode { font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: #8a94a0; margin: 0 0 4px; }
        h1 { font-size: 20px; font-weight: 700; margin: 0 0 10px; letter-spacing: -.01em; }
        .pesan { font-size: 13.5px; line-height: 1.65; color: #4a5158; margin: 0 0 18px; }
        .ref {
            display: inline-block;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px;
            background: #eef1f4;
            border: 1px solid #dfe4ea;
            border-radius: 7px;
            padding: 5px 10px;
            color: #4a5158;
            margin-bottom: 20px;
        }
        .aksi { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
        .tombol {
            appearance: none;
            border: 1px solid #23371d;
            background: #23371d;
            color: #fff;
            border-radius: 9px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            text-decoration: none;
            cursor: pointer;
            min-height: 42px;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .tombol--hantu { background: #fff; color: #23371d; }
        .lapor { margin-top: 22px; border-top: 1px solid #eef1f4; padding-top: 18px; text-align: left; }
        .lapor summary { font-size: 13px; font-weight: 600; cursor: pointer; color: #23371d; }
        .lapor textarea {
            width: 100%;
            margin-top: 10px;
            min-height: 78px;
            border: 1px solid #dfe4ea;
            border-radius: 9px;
            padding: 9px 11px;
            font: inherit;
            font-size: 13px;
            resize: vertical;
        }
        .lapor .bantu { font-size: 11.5px; color: #8a94a0; margin: 8px 0 10px; }
        .kabar {
            font-size: 12.5px;
            border-radius: 9px;
            padding: 9px 12px;
            margin-bottom: 16px;
            text-align: left;
        }
        .kabar--ok { background: #e8f3e4; color: #2c4722; border: 1px solid #cfe3c8; }
        .kabar--stop { background: #fdecec; color: #7d2b2b; border: 1px solid #f5cfcf; }
    </style>
</head>
<body>
    <main class="kotak">
        <div class="lambang"><i class="bi {{ $teks['ikon'] }}"></i></div>

        <p class="kode">Kesalahan {{ $status }}</p>
        <h1>{{ $teks['judul'] }}</h1>
        <p class="pesan">{{ $teks['pesan'] }}</p>

        @if (session('lapor_sukses'))
            <p class="kabar kabar--ok">{{ session('lapor_sukses') }}</p>
        @endif
        @if (session('lapor_gagal'))
            <p class="kabar kabar--stop">{{ session('lapor_gagal') }}</p>
        @endif

        @if ($teks['laporkan'] ?? false)
            <span class="ref">Kode referensi: {{ $ref }}</span>
        @endif

        <div class="aksi">
            <a class="tombol tombol--hantu" href="javascript:history.back()">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            <a class="tombol" href="{{ $user ? url('/dashboard') : url('/login') }}">
                <i class="bi bi-house"></i> {{ $user ? 'Ke Dashboard' : 'Ke Halaman Masuk' }}
            </a>
        </div>

        @if ($bisaLapor)
            <details class="lapor">
                <summary>Laporkan masalah ini ke admin</summary>
                <p class="bantu">
                    Ceritakan singkat apa yang sedang Anda lakukan saat kesalahan muncul.
                    Detail teknis dan kode referensi ikut terkirim otomatis.
                </p>
                <form method="POST" action="{{ route('laporan-error.store') }}">
                    @csrf
                    <textarea name="pesan" maxlength="1000"
                              placeholder="Contoh: saya menekan Simpan Presensi untuk kelas XII TKJ 1, lalu muncul halaman ini."></textarea>
                    <div class="aksi" style="justify-content: flex-start; margin-top: 10px">
                        <button class="tombol" type="submit">
                            <i class="bi bi-send"></i> Kirim Laporan
                        </button>
                    </div>
                </form>
            </details>
        @endif
    </main>
</body>
</html>
