@props(['user' => null, 'name' => null, 'size' => ''])
@php
    $label = $name ?? $user?->name;
    $initials = $label
        ? collect(preg_split('/\s+/', trim($label)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('')
        : '?';
    $tone = 'c'.((crc32((string) $label) % 5) + 1);
@endphp
<span {{ $attributes->class(['avatar', $size, $tone]) }} title="{{ $label }}">{{ $initials }}</span>
