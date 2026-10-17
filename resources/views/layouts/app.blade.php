@php
    use App\Support\Navigation;
    $user = auth()->user();
    $menus = Navigation::for($user);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ $companyName }} Finance</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{ sidebarOpen: false }">
    {{-- Ambient glow --}}
    <div class="fixed inset-0 pointer-events-none overflow-hidden z-0" aria-hidden="true">
        <div class="absolute -top-40 -left-40 w-96 h-96 rounded-full bg-secondary-container/30 blur-[120px]"></div>
        <div class="absolute top-1/3 -right-20 w-80 h-80 rounded-full bg-primary/10 blur-[100px]"></div>
        <div class="absolute -bottom-20 left-1/4 w-96 h-96 rounded-full bg-surface-container-high/40 blur-[130px]"></div>
    </div>

    {{-- Overlay mobile --}}
    <div x-cloak x-show="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 z-30 bg-background/70 backdrop-blur-sm lg:hidden"></div>

    {{-- Sidebar --}}
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
           class="fixed left-0 top-0 h-screen w-72 bg-surface-container-lowest/90 backdrop-blur-2xl z-40 flex flex-col justify-between shadow-[0_8px_32px_0_rgba(14,3,11,0.37)] transition-transform duration-200 lg:translate-x-0">
        <div class="flex flex-col flex-1 min-h-0">
            <a href="{{ route('dashboard') }}" class="h-16 px-space-lg flex items-center gap-space-sm shrink-0">
                <x-application-logo class="h-8 w-8" />
                <div class="flex flex-col leading-tight">
                    <span class="text-label-lg text-on-surface tracking-tight">{{ $companyName }}</span>
                    <span class="text-label-sm text-outline">Financial Executive</span>
                </div>
            </a>

            <nav class="flex-1 overflow-y-auto px-space-sm py-space-sm space-y-space-xs">
                @foreach ($menus as $menu)
                    @if (empty($menu['children']))
                        <a href="{{ route($menu['route']) }}" @class(['nav-link', 'nav-link-active' => request()->routeIs($menu['active'])])
                           @if (request()->routeIs($menu['active'])) aria-current="page" @endif>
                            <span class="material-symbols-outlined">{{ $menu['icon'] }}</span>
                            <span class="flex-1">{{ $menu['label'] }}</span>
                        </a>
                    @else
                        <details class="group" @if (Navigation::isGroupActive($menu)) open @endif>
                            <summary class="nav-link justify-between cursor-pointer select-none">
                                <span class="flex items-center gap-2">
                                    <span class="material-symbols-outlined">{{ $menu['icon'] }}</span>
                                    <span>{{ $menu['label'] }}</span>
                                    @if (($menu['badge'] ?? null) === 'pending' && $pendingApprovalCount > 0)
                                        <span class="px-1.5 py-0.5 rounded-full bg-secondary-container text-on-secondary-container text-label-sm">{{ $pendingApprovalCount }}</span>
                                    @endif
                                </span>
                                <span class="material-symbols-outlined text-[18px]! transition-transform duration-200 group-open:rotate-180">expand_more</span>
                            </summary>
                            <div class="mt-space-xs ml-space-md pl-space-sm space-y-space-xs">
                                @foreach ($menu['children'] as $child)
                                    <a href="{{ route($child['route'], $child['params'] ?? []) }}"
                                       @class(['subnav-link', 'nav-link-active' => Navigation::isActive($child)])>{{ $child['label'] }}</a>
                                @endforeach
                            </div>
                        </details>
                    @endif
                @endforeach
            </nav>
        </div>

        <div class="p-space-md m-space-sm rounded-xl bg-surface-container-low/70 backdrop-blur-md">
            <div class="flex items-center gap-space-sm">
                <span class="relative flex h-2.5 w-2.5 items-center justify-center">
                    @if ($currentPeriod?->isOpen())
                        <span class="absolute inline-flex h-full w-full rounded-full bg-primary/70 animate-ping opacity-75"></span>
                    @endif
                    <span class="relative inline-flex h-2 w-2 rounded-full {{ $currentPeriod?->isOpen() ? 'bg-primary' : 'bg-outline' }}"></span>
                </span>
                <div class="flex flex-col">
                    <span class="text-label-sm text-outline">Periode Aktif</span>
                    <span class="text-label-sm text-primary tracking-wide">
                        {{ $currentPeriod ? $currentPeriod->code.' ('.$currentPeriod->status->value.')' : 'Belum ada periode' }}
                    </span>
                </div>
            </div>
        </div>
    </aside>

    <div class="lg:pl-72 min-h-screen relative z-10">
        {{-- Header --}}
        <header class="sticky top-0 h-16 bg-surface-container-lowest/80 backdrop-blur-2xl z-20 flex items-center justify-between gap-space-md px-space-md sm:px-space-lg shadow-[0_4px_24px_rgba(14,3,11,0.25)]">
            <div class="flex items-center gap-space-sm flex-1 max-w-md">
                <button type="button" class="btn-ghost p-2! lg:hidden" @click="sidebarOpen = true" aria-label="Buka menu">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                @can('transaction.view')
                    <form method="GET" action="{{ route('transactions.index') }}" class="relative w-full hidden sm:block">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]!">search</span>
                        <input name="q" value="{{ request('q') }}" type="search"
                               class="w-full pl-9 pr-4 py-1.5 rounded-lg border-0 bg-surface-container-high/60 text-on-surface placeholder:text-outline text-body-sm focus:outline-none focus:ring-1 focus:ring-primary/40"
                               placeholder="Cari nomor transaksi, deskripsi, pihak...">
                    </form>
                @endcan
            </div>
            <div class="flex items-center gap-space-md">
                @if ($currentPeriod)
                    <a href="{{ route('master.periods.index') }}" class="hidden md:flex items-center gap-space-xs px-space-sm py-1 rounded-full bg-surface-container-high/70">
                        <span class="material-symbols-outlined text-[16px]! text-primary">calendar_today</span>
                        <span class="text-label-sm text-on-surface">Periode: {{ $currentPeriod->start_date->translatedFormat('M Y') }}</span>
                        <span class="text-label-sm uppercase {{ $currentPeriod->isOpen() ? 'text-primary' : 'text-outline' }}">[{{ $currentPeriod->status->value }}]</span>
                    </a>
                @endif
                <div class="h-6 w-px bg-surface-variant/40 hidden md:block"></div>
                <x-dropdown align="right" width="56">
                    <x-slot name="trigger">
                        <button type="button" class="flex items-center gap-space-sm cursor-pointer">
                            <span class="hidden sm:flex flex-col text-right">
                                <span class="text-label-md text-on-surface">{{ $user->name }}</span>
                                <span class="text-label-sm text-outline">{{ $user->role->label() }}</span>
                            </span>
                            <span class="w-9 h-9 rounded-full bg-secondary-container text-on-secondary-container ring-1 ring-primary/30 flex items-center justify-center text-label-md">{{ $user->initials() }}</span>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            <span class="material-symbols-outlined text-[18px]!">person</span> Profil Saya
                        </x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                <span class="material-symbols-outlined text-[18px]!">logout</span> Keluar
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>
        </header>

        <main class="w-full px-space-md sm:px-gutter py-space-lg pb-space-xl">
            <x-flash />
            {{ $slot }}
        </main>
    </div>
</body>
</html>
