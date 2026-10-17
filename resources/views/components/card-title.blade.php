@props(['icon' => null, 'title', 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'flex items-start justify-between gap-space-md mb-space-md']) }}>
    <div class="flex items-start gap-space-sm min-w-0">
        @if ($icon)
            <span class="w-10 h-10 rounded-xl bg-secondary-container/50 text-primary flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined">{{ $icon }}</span>
            </span>
        @endif
        <div class="min-w-0">
            <h2 class="text-headline-sm text-on-surface tracking-tight">{{ $title }}</h2>
            @if ($subtitle)
                <p class="text-body-sm text-on-surface-variant mt-0.5">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    @isset($actions)
        <div class="flex items-center gap-space-sm shrink-0">{{ $actions }}</div>
    @endisset
</div>
