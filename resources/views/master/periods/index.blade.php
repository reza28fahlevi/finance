@php use App\Enums\PeriodStatus; @endphp
<x-app-layout title="Periode Akuntansi">
    <section class="glass-card p-space-lg mb-space-lg bg-gradient-to-br from-secondary-container/30 to-surface-container/60">
        <x-page-header eyebrow="Master Data Kontrol" context="Integritas GL" title="Periode Akuntansi"
                       subtitle="Kelola status buka/tutup periode pembukuan untuk kontrol integritas posting transaksi dan kepatuhan audit keuangan." class="mb-0!">
            <x-slot:actions>
                @can('master.manage')
                    <form method="POST" action="{{ route('master.periods.store') }}" class="flex items-center gap-space-sm">
                        @csrf
                        <input type="month" name="code" value="{{ old('code', $suggestedCode) }}" class="form-input w-44" required aria-label="Kode periode baru">
                        <button class="btn-primary"><span class="material-symbols-outlined">add_circle</span> Buat Periode Baru</button>
                    </form>
                @endcan
            </x-slot:actions>
        </x-page-header>
    </section>

    <section class="grid grid-cols-1 md:grid-cols-3 gap-space-md mb-space-lg">
        <div class="glass-card p-space-lg">
            <div class="flex items-center justify-between mb-space-sm">
                <span class="flex items-center gap-2 text-label-lg"><span class="material-symbols-outlined text-primary">calendar_month</span>Periode Aktif Berjalan</span>
                @if ($current)<x-status-badge :status="$current->status" />@endif
            </div>
            <p class="text-display-lg-mobile sm:text-display-lg text-on-surface">{{ $current?->code ?? '—' }}</p>
            <p class="text-body-md text-on-surface-variant">{{ $current?->label() }}</p>
        </div>
        <div class="glass-card p-space-lg">
            <span class="flex items-center gap-2 text-label-lg mb-space-sm"><span class="material-symbols-outlined text-primary">hourglass_top</span>Sisa Hari Transaksi</span>
            @php
                $daysLeft = $current && $current->isOpen() ? max(0, (int) now()->startOfDay()->diffInDays($current->end_date, false)) : null;
                $totalDays = $current ? $current->start_date->diffInDays($current->end_date) + 1 : 1;
            @endphp
            <p class="text-display-lg-mobile sm:text-display-lg">{{ $daysLeft ?? '—' }} <span class="text-headline-sm text-primary">Hari Tersisa</span></p>
            @if ($current)
                <div class="mt-space-sm h-1.5 rounded-full bg-surface-container-lowest overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-primary-container to-secondary" style="width: {{ $daysLeft === null ? 100 : round((($totalDays - $daysLeft) / $totalDays) * 100) }}%"></div>
                </div>
                <p class="mt-1 text-label-sm text-on-surface-variant">Target cut-off: {{ $current->end_date->translatedFormat('d M Y') }}</p>
            @endif
        </div>
        <div class="glass-card p-space-lg">
            <div class="flex items-center justify-between mb-space-sm">
                <span class="flex items-center gap-2 text-label-lg"><span class="material-symbols-outlined text-primary">verified_user</span>Integritas Buku Besar</span>
                <span class="badge bg-surface-container-highest text-on-surface-variant">Immutable</span>
            </div>
            <p class="text-display-lg-mobile sm:text-display-lg">{{ $closedCount }} <span class="text-headline-sm">Periode CLOSED</span></p>
            <p class="text-label-sm text-on-surface-variant">{{ $openCount }} periode masih OPEN</p>
        </div>
    </section>

    <section class="glass-card p-space-md mb-space-lg flex items-center gap-space-sm flex-wrap">
        <a href="{{ route('master.periods.index') }}" @class(['chip', 'chip-active' => ! $status && ! $year])>Semua</a>
        <a href="{{ route('master.periods.index', ['status' => 'OPEN']) }}" @class(['chip', 'chip-active' => $status === PeriodStatus::Open])>Hanya Terbuka ({{ $openCount }})</a>
        <a href="{{ route('master.periods.index', ['status' => 'CLOSED']) }}" @class(['chip', 'chip-active' => $status === PeriodStatus::Closed])>Terkunci ({{ $closedCount }})</a>
        @foreach ($years as $y)
            <a href="{{ route('master.periods.index', ['year' => $y]) }}" @class(['chip', 'chip-active' => $year === (int) $y])>Tahun {{ $y }}</a>
        @endforeach
    </section>

    <section class="glass-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr><th>Kode Periode</th><th>Rentang Tanggal</th><th class="text-right">Jurnal Terposting</th><th class="text-right">Draft</th><th class="text-center">Status</th><th>Tanggal Tutup Buku</th><th>Ditutup Oleh</th><th class="text-right">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse ($periods as $period)
                        <tr>
                            <td>
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined {{ $period->isOpen() ? 'text-primary-container' : 'text-outline' }}">{{ $period->isOpen() ? 'radio_button_checked' : 'check_circle' }}</span>
                                    <div><p class="text-label-lg">{{ $period->code }}</p><p class="text-body-sm text-on-surface-variant">{{ $period->label() }}</p></div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap">{{ $period->start_date->translatedFormat('d M Y') }} — {{ $period->end_date->translatedFormat('d M Y') }}</td>
                            <td class="text-right"><span class="text-label-lg">{{ number_format($period->posted_journals_count, 0, ',', '.') }}</span> <span class="text-body-sm text-on-surface-variant">jurnal</span></td>
                            <td class="text-right">{{ $period->draft_journals_count }}</td>
                            <td class="text-center"><x-status-badge :status="$period->status" /></td>
                            <td class="whitespace-nowrap text-on-surface-variant">{{ $period->closed_at ? $period->closed_at->translatedFormat('d M Y • H:i') : '— Belum Ditutup —' }}</td>
                            <td>
                                @if ($period->closedBy)
                                    <span class="flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-secondary-container text-on-secondary-container text-label-sm flex items-center justify-center">{{ $period->closedBy->initials() }}</span>{{ $period->closedBy->name }}</span>
                                @else
                                    <span class="text-on-surface-variant">Belum Ada</span>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                @if ($period->isOpen())
                                    @can('period.close')
                                        <form method="POST" action="{{ route('master.periods.close', $period) }}" onsubmit="return confirm('Tutup buku periode {{ $period->code }}? Setelah CLOSED, tidak ada transaksi/jurnal/posting yang dapat dilakukan.')">
                                            @csrf
                                            <button class="btn-secondary btn-sm"><span class="material-symbols-outlined text-[16px]!">lock</span> Tutup Buku</button>
                                        </form>
                                    @endcan
                                @else
                                    @can('period.reopen')
                                        <form method="POST" action="{{ route('master.periods.reopen', $period) }}" x-data="{ open: false }" class="inline-flex items-center gap-1">
                                            @csrf
                                            <input x-cloak x-show="open" name="reason" class="form-input py-1! w-48" placeholder="Alasan buka kembali" required minlength="5">
                                            <button type="button" x-show="!open" @click="open = true" class="btn-ghost btn-sm"><span class="material-symbols-outlined text-[16px]!">lock_open</span> Buka Kembali</button>
                                            <button x-cloak x-show="open" class="btn-danger btn-sm">Konfirmasi</button>
                                        </form>
                                    @else
                                        <span class="text-label-sm text-outline flex items-center justify-end gap-1"><span class="material-symbols-outlined text-[16px]!">lock</span>Read-only</span>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty-state icon="event_busy" title="Belum ada periode" message="Buat periode akuntansi pertama untuk mulai mencatat transaksi." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-space-md">{{ $periods->links() }}</div>
    </section>

    <section class="mt-space-lg glass-card p-space-lg">
        <div class="flex items-start gap-space-md">
            <span class="w-12 h-12 rounded-2xl bg-secondary-container/60 text-primary flex items-center justify-center shrink-0"><span class="material-symbols-outlined">gavel</span></span>
            <div>
                <h2 class="text-headline-sm">Kebijakan & Aturan Pembukuan</h2>
                <p class="text-body-md text-on-surface-variant mt-1"><b class="text-primary">Aturan Periode:</b> Transaksi dan jurnal hanya dapat dibuat & diposting pada periode <b>OPEN</b>. Periode <b>CLOSED</b> bersifat read-only. Penutupan ditolak bila masih ada jurnal Draft atau transaksi Pending/Approved. Pembukaan kembali hanya oleh Admin dengan alasan yang tercatat di audit log.</p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-space-sm mt-space-md">
            @foreach ([['lock', 'Immutable Ledger', 'Tidak ada perubahan ledger di periode CLOSED.'], ['verified', 'Maker–Checker', 'Setiap transaksi diverifikasi checker berbeda.'], ['history', 'Forensic Logging', 'Setiap perubahan status tercatat di audit log.']] as [$icon, $t, $d])
                <div class="glass-panel p-space-md flex items-start gap-2">
                    <span class="material-symbols-outlined text-primary">{{ $icon }}</span>
                    <div><p class="text-label-lg">{{ $t }}</p><p class="text-body-sm text-on-surface-variant">{{ $d }}</p></div>
                </div>
            @endforeach
        </div>
    </section>
</x-app-layout>
