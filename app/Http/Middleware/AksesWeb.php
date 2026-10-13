<?php

namespace App\Http\Middleware;

use App\Models\Siswa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AksesWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isSiswa()) {
            $ketua = Siswa::whereKey($user->nis)
                ->whereHas('kelas', fn ($q) => $q->where('ketua_nis', $user->nis))
                ->exists();

            abort_unless($ketua, 403, 'Akses web siswa hanya tersedia untuk ketua kelas.');
        }

        return $next($request);
    }
}
