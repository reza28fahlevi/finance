@php use App\Support\Money; @endphp
<x-app-layout title="Jurnal Pembalik">
    <x-page-header eyebrow="Modul Akuntansi" context="Koreksi Immutable" title="Jurnal Pembalik" accent="(Reversal)"
                   subtitle="Buat jurnal pembalik dari jurnal POSTED (debit/kredit ditukar) untuk koreksi. Jurnal pembalik langsung diposting dan jurnal asli berstatus REVERSED." />

    <section class="glass-card p-space-md mb-space-lg">
        <form method="GET" class="flex items-center gap-space-sm">
            <div class="relative flex-1"><span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]!">search</span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-input pl-9" placeholder="Cari no. referensi jurnal POSTED..."></div>
            <button class="btn-secondary">Cari</button>
        </form>
    </section>

    <section class="glass-card overflow-hidden mb-space-lg">
        <div class="p-space-md"><h2 class="text-headline-sm">Jurnal POSTED yang dapat dibalik</h2></div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>No. Referensi</th><th>Tanggal</th><th>Periode</th><th>Deskripsi</th><th class="text-right">Total</th>@can('journal.manage')<th class="w-[28rem]">Buat Pembalik</th>@endcan</tr></thead>
                <tbody>
                    @forelse ($posted as $j)
                        <tr>
                            <td><a href="{{ route('journals.show', $j) }}" class="font-mono text-primary hover:underline">{{ $j->ref_no }}</a></td>
                            <td class="whitespace-nowrap">{{ $j->entry_date->translatedFormat('d M Y') }}</td>
                            <td>{{ $j->period->code }}</td>
                            <td class="max-w-72"><p class="truncate">{{ $j->description }}</p></td>
                            <td class="text-right whitespace-nowrap">{{ Money::format($j->total_debit ?? 0) }}</td>
                            @can('journal.manage')
                                <td>
                                    <form method="POST" action="{{ route('journals.reverse', $j) }}" class="flex items-center gap-2" onsubmit="return confirm('Buat & posting jurnal pembalik untuk {{ $j->ref_no }}?')">
                                        @csrf
                                        <input type="date" name="entry_date" value="{{ now()->toDateString() }}" class="form-input py-1.5! w-40" required aria-label="Tanggal pembalik">
                                        <input name="reason" class="form-input py-1.5!" placeholder="Alasan koreksi" required minlength="5" maxlength="500" aria-label="Alasan">
                                        <button class="btn-primary btn-sm whitespace-nowrap"><span class="material-symbols-outlined text-[16px]!">undo</span>Balik</button>
                                    </form>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="undo" title="Tidak ada jurnal POSTED" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-space-md">{{ $posted->links() }}</div>
    </section>

    <section class="glass-card overflow-hidden">
        <div class="p-space-md"><h2 class="text-headline-sm">Riwayat Jurnal Pembalik</h2></div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Jurnal Pembalik</th><th>Membalik</th><th>Tanggal</th><th>Alasan / Deskripsi</th><th class="text-right">Total</th><th>Oleh</th></tr></thead>
                <tbody>
                    @forelse ($history as $r)
                        <tr>
                            <td><a href="{{ route('journals.show', $r) }}" class="font-mono text-primary hover:underline">{{ $r->ref_no }}</a></td>
                            <td><a href="{{ route('journals.show', $r->reversalOf) }}" class="font-mono hover:underline">{{ $r->reversalOf->ref_no }}</a></td>
                            <td class="whitespace-nowrap">{{ $r->entry_date->translatedFormat('d M Y') }}</td>
                            <td class="max-w-96 text-body-sm">{{ $r->description }}</td>
                            <td class="text-right whitespace-nowrap">{{ Money::format($r->total_debit ?? 0) }}</td>
                            <td>{{ $r->creator->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="history" title="Belum ada jurnal pembalik" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-app-layout>
