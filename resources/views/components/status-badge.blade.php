{{-- Badge status transaksi / jurnal / periode. Menerima enum dengan badgeClass() atau string. --}}
@props(['status'])

@php
    $label = method_exists($status, 'label') ? $status->label() : ($status->value ?? (string) $status);
    $class = method_exists($status, 'badgeClass')
        ? $status->badgeClass()
        : match ($status->value ?? (string) $status) {
            'OPEN' => 'bg-secondary-container/70 text-on-secondary-container',
            'CLOSED' => 'bg-surface-container-highest text-outline',
            default => 'bg-surface-container-highest text-on-surface-variant',
        };
@endphp

<span {{ $attributes->merge(['class' => "badge badge-dot whitespace-nowrap {$class}"]) }}>{{ $label }}</span>
