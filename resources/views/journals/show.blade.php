@php use App\Support\Money; use App\Enums\JournalStatus; use App\Enums\JournalSource; @endphp
<x-app-layout :title="$journal->ref_no">
    <x-page-header :eyebrow="'Jurnal '.$journal->source->label()" :context="'Periode '.$journal->period->code" :title="$journal->ref_no" :subtitle="$journal->description">
        <x-slot:actions>
            <a href="{{ route('journals.index') }}" class="btn-secondary"><span class="material-symbols-outlined">list</span> Daftar Jurnal</a>
            @can('journal.manage')
                @if ($journal->status === JournalStatus::Draft)
                    @if ($journal->source === JournalSource::Manual)
                        <a href="{{ route('journals.edit', $journal) }}" class="btn-secondary"><span class="material-symbols-outlined">edit</span> Edit</a>
                        <form method="POST" action="{{ route('journals.destroy', $journal) }}" onsubmit="return confirm('Hapus jurnal draft ini?')">@csrf @method('DELETE')
                            <button class="btn-danger"><span class="material-symbols-outlined">delete</span> Hapus</button></form>
                    @endif
                    <form method="POST" action="{{ route('journals.post', $journal) }}">@csrf
                        <button class="btn-primary"><span class="material-symbols-outlined">check_circle</span> Posting ke GL</button></form>
                @elseif ($journal->status === JournalStatus::Posted && ! $journal->reversedBy)
                    <a href="{{ route('journals.reversals', ['q' => $journal->ref_no]) }}" class="btn-secondary"><span class="material-symbols-outlined">undo</span> Buat Jurnal Pembalik</a>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-space-lg">
        <section class="xl:col-span-2 glass-card p-space-lg">
            <x-card-title icon="table_rows" title="Rincian Baris Jurnal" :subtitle="$journal->lines->count().' baris'">
                <x-slot:actions><x-status-badge :status="$journal->status" /></x-slot:actions>
            </x-card-title>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>No</th><th>Kode & Nama Akun</th><th>Deskripsi Baris</th><th class="text-right">Debit (Rp)</th><th class="text-right">Kredit (Rp)</th></tr></thead>
                    <tbody>
                        @foreach ($journal->lines as $line)
                            <tr>
                                <td class="text-on-surface-variant">{{ $line->line_no }}</td>
                                <td class="whitespace-nowrap"><span class="font-mono">{{ $line->account->code }}</span> | {{ $line->account->name }}</td>
                                <td class="text-body-sm text-on-surface-variant">{{ $line->description ?: '—' }}</td>
                                <td class="text-right font-mono">{{ Money::isPositive($line->debit) ? Money::format($line->debit, false) : '' }}</td>
                                <td class="text-right font-mono">{{ Money::isPositive($line->credit) ? Money::format($line->credit, false) : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-surface-container-highest/50">
                            <td colspan="3" class="px-4 py-3 text-right text-label-md uppercase">Total</td>
                            <td class="px-4 py-3 text-right font-mono font-bold">{{ Money::format($journal->totalDebit(), false) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold">{{ Money::format($journal->totalCredit(), false) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="mt-space-sm text-label-md flex items-center gap-1 {{ $journal->isBalanced() ? 'text-primary' : 'text-error' }}">
                <span class="material-symbols-outlined text-[16px]!">{{ $journal->isBalanced() ? 'verified' : 'warning' }}</span>
                {{ $journal->isBalanced() ? 'Seimbang (Debit = Kredit)' : 'Tidak seimbang' }}
            </p>
        </section>

        <aside class="glass-card p-space-lg">
            <x-card-title icon="info" title="Informasi Jurnal" />
            <dl class="space-y-space-sm text-body-md">
                <div class="flex justify-between gap-2"><dt class="text-on-surface-variant">Tanggal Efektif</dt><dd>{{ $journal->entry_date->translatedFormat('d M Y') }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-on-surface-variant">Periode</dt><dd>{{ $journal->period->code }} <x-status-badge :status="$journal->period->status" /></dd></div>
                <div class="flex justify-between gap-2"><dt class="text-on-surface-variant">Sumber</dt><dd>{{ $journal->source->label() }}</dd></div>
                @if ($journal->transaction)
                    <div class="flex justify-between gap-2"><dt class="text-on-surface-variant">Transaksi</dt><dd><a href="{{ route('transactions.show', $journal->transaction) }}" class="font-mono text-primary hover:underline">{{ $journal->transaction->number }}</a></dd></div>
                @endif
                <div class="flex justify-between gap-2"><dt class="text-on-surface-variant">Dibuat oleh</dt><dd>{{ $journal->creator->name }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-on-surface-variant">Dibuat</dt><dd>{{ $journal->created_at->translatedFormat('d M Y H:i') }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-on-surface-variant">Diposting oleh</dt><dd>{{ $journal->poster?->name ?? '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-on-surface-variant">Waktu Posting</dt><dd>{{ $journal->posted_at?->translatedFormat('d M Y H:i') ?? '—' }}</dd></div>
                @if ($journal->reversalOf)
                    <div class="flex justify-between gap-2"><dt class="text-on-surface-variant">Membalik jurnal</dt><dd><a href="{{ route('journals.show', $journal->reversalOf) }}" class="font-mono text-primary hover:underline">{{ $journal->reversalOf->ref_no }}</a></dd></div>
                @endif
                @if ($journal->reversedBy)
                    <div class="flex justify-between gap-2"><dt class="text-on-surface-variant">Dibalik oleh</dt><dd><a href="{{ route('journals.show', $journal->reversedBy) }}" class="font-mono text-primary hover:underline">{{ $journal->reversedBy->ref_no }}</a></dd></div>
                @endif
            </dl>
            @if ($journal->status !== JournalStatus::Draft)
                <p class="mt-space-md p-space-sm rounded-lg bg-secondary-container/25 text-body-sm text-on-surface-variant flex gap-2"><span class="material-symbols-outlined text-[16px]! text-primary">lock</span>Jurnal terkunci (immutable). Koreksi hanya melalui jurnal pembalik.</p>
            @endif
        </aside>
    </div>
</x-app-layout>
