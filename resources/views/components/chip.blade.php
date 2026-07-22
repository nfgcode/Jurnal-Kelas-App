@props(['tone' => 'neutral', 'label' => null])

<span {{ $attributes->class(['chip', 'chip--' . $tone]) }}>{{ $label ?? $slot }}</span>
