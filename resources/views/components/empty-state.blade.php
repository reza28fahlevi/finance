@props(['icon' => 'inbox', 'title' => 'Belum ada data', 'message' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center text-center py-space-xl px-space-md gap-space-sm']) }}>
    <span class="w-14 h-14 rounded-2xl bg-surface-container-high/60 flex items-center justify-center text-primary">
        <span class="material-symbols-outlined text-[28px]!">{{ $icon }}</span>
    </span>
    <p class="text-label-lg text-on-surface">{{ $title }}</p>
    @if ($message)
        <p class="text-body-sm text-on-surface-variant max-w-md">{{ $message }}</p>
    @endif
    {{ $slot }}
</div>
