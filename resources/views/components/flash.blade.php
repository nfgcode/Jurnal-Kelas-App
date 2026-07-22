@if (session('success'))
    <div class="flash flash--success"><i class="bi bi-check-circle-fill"></i>{{ session('success') }}</div>
@endif

@if (session('error'))
    <div class="flash flash--error"><i class="bi bi-exclamation-circle-fill"></i>{{ session('error') }}</div>
@endif
