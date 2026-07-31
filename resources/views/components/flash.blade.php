@if (session('success'))
    <div class="flash flash--success"><x-ikon nama="check-circle-fill" />{{ session('success') }}</div>
@endif

@if (session('error'))
    <div class="flash flash--error"><x-ikon nama="exclamation-circle-fill" />{{ session('error') }}</div>
@endif
