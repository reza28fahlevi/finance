@props(['label', 'value', 'icon' => null, 'hint' => null, 'tone' => 'primary'])

@php
    $iconClass = match ($tone) {
        'secondary' => 'bg-primary-container/20 text-primary-container',
        'tertiary' => 'bg-surface-container-highest/60 text-tertiary',
        default => 'bg-secondary-container/40 text-primary',
    };
@endphp

<div {{ $attributes->merge(['class' => 'p-space-lg glass-card hover:bg-surface-container-high/60 transition-all duration-300 flex flex-col justify-between gap-space-sm group']) }}>
    <div class="flex items-center justify-between gap-space-sm">
        <span class="text-label-sm uppercase tracking-wider text-on-surface-variant">{{ $label }}</span>
        @if ($icon)
            <span class="w-9 h-9 rounded-xl {{ $iconClass }} flex items-center justify-center group-hover:scale-110 transition-transform shrink-0">
                <span class="material-symbols-outlined">{{ $icon }}</span>
            </span>
        @endif
    </div>
    <div>
        <div class="text-headline-md font-bold text-on-surface tracking-tight break-words">{{ $value }}</div>
        @if ($hint || $slot->isNotEmpty())
            <div class="mt-space-xs text-body-sm text-on-surface-variant flex items-center gap-1.5 flex-wrap">{{ $hint }}{{ $slot }}</div>
        @endif
    </div>
</div>
