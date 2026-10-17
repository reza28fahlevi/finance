@props(['eyebrow' => null, 'context' => null, 'title', 'accent' => null, 'subtitle' => null])

<section {{ $attributes->merge(['class' => 'flex flex-col lg:flex-row lg:items-end lg:justify-between gap-space-md mb-space-lg']) }}>
    <div class="flex flex-col gap-space-xs min-w-0">
        @if ($eyebrow)
            <div class="flex items-center gap-space-xs flex-wrap">
                <span class="eyebrow">{{ $eyebrow }}</span>
                @if ($context)
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-primary-container"></span>
                    <span class="text-label-sm text-on-surface-variant">{{ $context }}</span>
                @endif
            </div>
        @endif
        <h1 class="text-headline-lg-mobile sm:text-headline-lg text-on-surface tracking-tight">
            {{ $title }}@if ($accent) <span class="text-primary-container/80 font-normal">{{ $accent }}</span>@endif
        </h1>
        @if ($subtitle)
            <p class="text-body-md text-on-surface-variant max-w-3xl">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex items-center gap-space-sm flex-wrap shrink-0">{{ $actions }}</div>
    @endisset
</section>
