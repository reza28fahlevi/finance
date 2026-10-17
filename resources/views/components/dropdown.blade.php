@props(['align' => 'right', 'width' => '48'])

@php
$alignmentClasses = $align === 'left' ? 'origin-top-left start-0' : 'origin-top-right end-0';
$width = match ($width) { '48' => 'w-48', '56' => 'w-56', default => $width };
@endphp

<div class="relative" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
    <div @click="open = ! open">{{ $trigger }}</div>
    <div x-cloak x-show="open" x-transition.origin.top.right
         class="absolute z-50 mt-2 {{ $width }} {{ $alignmentClasses }} rounded-xl py-1 bg-surface-container-high/95 backdrop-blur-2xl ring-1 ring-primary/30 shadow-[0_12px_40px_0_rgba(14,3,11,0.5)]"
         @click="open = false">
        {{ $content }}
    </div>
</div>
