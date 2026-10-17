{{-- Pembungkus field form: label + kontrol (slot) + pesan error. --}}
@props(['label', 'name', 'hint' => null, 'required' => false])

<div {{ $attributes }}>
    <label for="{{ $name }}" class="form-label">
        {{ $label }}@if ($required)<span class="text-primary-container"> *</span>@endif
    </label>
    {{ $slot }}
    @if ($hint)
        <p class="mt-1 text-body-sm text-on-surface-variant/70">{{ $hint }}</p>
    @endif
    @error(str_replace(['[', ']'], ['.', ''], $name))
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
