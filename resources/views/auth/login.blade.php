@extends('layouts.guest')

@section('title', 'Login')

@section('content')
<div class="w-100" style="max-width: 420px;">
    <div class="card border-0 shadow-lg" style="border-radius: 16px;">
        <div class="card-body p-4 p-md-5">
            {{-- App branding --}}
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                     style="width: 60px; height: 60px; background: linear-gradient(135deg, #4361ee, #3f37c9);">
                    <i class="bi bi-journal-bookmark-fill text-white fs-3"></i>
                </div>
                <h4 class="fw-bold text-dark mb-1">Jurnal Kelas</h4>
                <p class="text-muted small">Masuk ke akun Anda</p>
            </div>

            {{-- Validation Errors --}}
            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show py-2 px-3" role="alert">
                    <ul class="mb-0 small list-unstyled">
                        @foreach ($errors->all() as $error)
                            <li><i class="bi bi-exclamation-circle me-1"></i>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            {{-- Login Form --}}
            <form method="POST" action="{{ route('login') }}">
                @csrf

                {{-- Email --}}
                <div class="mb-3">
                    <label for="email" class="form-label fw-medium small">Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="bi bi-envelope text-muted"></i>
                        </span>
                        <input type="email"
                               id="email"
                               name="email"
                               class="form-control border-start-0 @error('email') is-invalid @enderror"
                               value="{{ old('email') }}"
                               placeholder="nama@email.com"
                               required
                               autofocus>
                    </div>
                </div>

                {{-- Password --}}
                <div class="mb-3">
                    <label for="password" class="form-label fw-medium small">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="bi bi-lock text-muted"></i>
                        </span>
                        <input type="password"
                               id="password"
                               name="password"
                               class="form-control border-start-0 @error('password') is-invalid @enderror"
                               placeholder="••••••••"
                               required>
                    </div>
                </div>

                {{-- Remember Me --}}
                <div class="mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember"
                               {{ old('remember') ? 'checked' : '' }}>
                        <label class="form-check-label small" for="remember">
                            Ingat Saya
                        </label>
                    </div>
                </div>

                {{-- Submit --}}
                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold"
                        style="background: linear-gradient(135deg, #4361ee, #3f37c9); border: none; border-radius: 10px;">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
                </button>
            </form>
        </div>
    </div>

    <p class="text-center text-white-50 small mt-4">&copy; {{ date('Y') }} Jurnal Kelas. All rights reserved.</p>
</div>
@endsection
