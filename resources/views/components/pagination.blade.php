@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navigasi halaman" class="flex items-center justify-between gap-space-md flex-wrap">
        <p class="text-label-md text-on-surface-variant">
            Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }} data
        </p>
        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="px-2 py-1 rounded-lg text-outline/50"><span class="material-symbols-outlined">chevron_left</span></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="px-2 py-1 rounded-lg text-on-surface-variant hover:bg-surface-container-high" aria-label="Sebelumnya"><span class="material-symbols-outlined">chevron_left</span></a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-3 py-1 text-outline">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="min-w-9 text-center px-3 py-1.5 rounded-lg bg-primary-container text-on-primary-container text-label-md">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="min-w-9 text-center px-3 py-1.5 rounded-lg text-on-surface-variant hover:bg-surface-container-high text-label-md">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="px-2 py-1 rounded-lg text-on-surface-variant hover:bg-surface-container-high" aria-label="Berikutnya"><span class="material-symbols-outlined">chevron_right</span></a>
            @else
                <span class="px-2 py-1 rounded-lg text-outline/50"><span class="material-symbols-outlined">chevron_right</span></span>
            @endif
        </div>
    </nav>
@endif
