@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'text-body-sm text-primary']) }}>{{ $status }}</div>
@endif
