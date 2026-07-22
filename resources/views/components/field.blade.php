@props(['label' => null, 'name' => null, 'required' => false, 'hint' => null])

<div class="field">
    @if ($label)
        <label class="field__label" @if ($name) for="{{ $name }}" @endif>
            {{ $label }}@if ($required)<span class="field__req"> *</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint)
        <span class="field__hint">{{ $hint }}</span>
    @endif

    @if ($name)
        @error($name)<span class="field__error">{{ $message }}</span>@enderror
    @endif
</div>
