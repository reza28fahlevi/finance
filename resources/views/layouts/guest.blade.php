<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk · {{ app(\App\Support\Settings::class)->get('company_name') }} Finance</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="fixed inset-0 pointer-events-none overflow-hidden z-0" aria-hidden="true">
        <div class="absolute -top-40 -left-40 w-[32rem] h-[32rem] rounded-full bg-secondary-container/35 blur-[120px]"></div>
        <div class="absolute top-1/4 -right-20 w-96 h-96 rounded-full bg-primary-container/15 blur-[110px]"></div>
        <div class="absolute -bottom-32 left-1/3 w-[28rem] h-[28rem] rounded-full bg-surface-container-high/50 blur-[130px]"></div>
    </div>

    <main class="relative z-10 min-h-screen grid lg:grid-cols-2">
        <section class="hidden lg:flex flex-col justify-between p-space-xl">
            <div class="flex items-center gap-space-sm">
                <x-application-logo class="h-10 w-10" />
                <div class="flex flex-col leading-tight">
                    <span class="text-label-lg text-on-surface">{{ app(\App\Support\Settings::class)->get('company_name') }}</span>
                    <span class="text-label-sm text-outline">Financial Executive</span>
                </div>
            </div>
            <div class="max-w-lg space-y-space-md">
                <span class="eyebrow">Input → General Ledger</span>
                <h1 class="text-display-lg text-on-surface">Pembukuan yang rapi, <span class="text-primary-container">terkendali</span>, dan dapat diaudit.</h1>
                <p class="text-body-lg text-on-surface-variant">Transaksi, approval maker–checker, jurnal seimbang, hingga buku besar — dalam satu alur.</p>
                <ul class="grid grid-cols-2 gap-space-sm pt-space-sm">
                    @foreach ([['verified_user', 'Segregation of Duties'], ['balance', 'Debit = Kredit'], ['lock', 'Jurnal Immutable'], ['history', 'Audit Trail']] as [$icon, $text])
                        <li class="flex items-center gap-2 p-space-sm rounded-xl bg-surface-container/50 backdrop-blur-xl text-label-md text-on-surface-variant">
                            <span class="material-symbols-outlined text-primary">{{ $icon }}</span>{{ $text }}
                        </li>
                    @endforeach
                </ul>
            </div>
            <p class="text-label-sm text-outline">&copy; {{ date('Y') }} {{ app(\App\Support\Settings::class)->get('company_name') }}</p>
        </section>

        <section class="flex items-center justify-center p-space-md sm:p-space-xl">
            <div class="w-full max-w-md">
                <div class="flex lg:hidden items-center gap-space-sm mb-space-lg justify-center">
                    <x-application-logo class="h-10 w-10" />
                    <span class="text-label-lg text-on-surface">{{ app(\App\Support\Settings::class)->get('company_name') }}</span>
                </div>
                <div class="rounded-3xl bg-surface-container/60 backdrop-blur-2xl ring-1 ring-primary/15 shadow-[0_24px_64px_0_rgba(0,0,0,0.5)] p-space-lg sm:p-space-xl">
                    {{ $slot }}
                </div>
            </div>
        </section>
    </main>
</body>
</html>
