{{-- Laravel's conventional error view for HTTP 403: forwards to the shared
     friendly page. Used when APP_DEBUG=false (production), where every role —
     including admin — gets the friendly page instead of a stack trace. --}}
@include('errors.ramah', [
    'status' => 403,
    'ref' => session('sistem.error_terakhir.ref', \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(8))),
    'teks' => \App\Support\PesanError::untuk(403),
])
