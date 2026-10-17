@php use App\Support\Money; @endphp
<x-app-layout title="Posting Batch">
    <x-page-header eyebrow="Modul Akuntansi" context="General Ledger" title="Posting Batch Jurnal"
                   subtitle="Pilih beberapa jurnal Draft lalu posting sekaligus. Setiap jurnal diproses dalam transaksi database terpisah; hasil sukses/gagal ditampilkan per jurnal." />

    <form method="POST" action="{{ route('journals.batch.post') }}" x-data="{ selected: [], all: @js($drafts->pluck('id')->map(fn ($id) => (string) $id)->values()) }">
        @csrf
        <section class="glass-card p-space-md mb-space-lg flex items-center justify-between gap-space-md flex-wrap">
            <div class="flex items-center gap-space-sm">
                <label class="inline-flex items-center gap-2 cursor-pointer text-label-md">
                    <input type="checkbox" class="form-check" @change="selected = $event.target.checked ? [...all] : []" :checked="selected.length && selected.length === all.length"> Pilih semua
                </label>
                <span class="text-label-md text-on-surface-variant"><span x-text="selected.length"></span> dari {{ $drafts->count() }} jurnal dipilih</span>
            </div>
            <div class="flex items-center gap-space-sm">
                <select class="form-input w-48" onchange="window.location = '{{ route('journals.batch') }}' + (this.value ? '?period=' + this.value : '')" aria-label="Filter periode">
                    <option value="">Semua Periode</option>
                    @foreach ($periods as $p)<option value="{{ $p->id }}" @selected((int) request('period') === $p->id)>{{ $p->code }} ({{ $p->status->value }})</option>@endforeach
                </select>
                <button class="btn-primary" :disabled="!selected.length"><span class="material-symbols-outlined">auto_read_play</span> Posting Terpilih</button>
            </div>
        </section>

        <section class="glass-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th class="w-10"></th><th>No. Referensi</th><th>Tanggal</th><th>Periode</th><th>Sumber</th><th>Deskripsi</th><th class="text-right">Debit</th><th class="text-right">Kredit</th><th class="text-center">Seimbang</th></tr></thead>
                    <tbody>
                        @forelse ($drafts as $j)
                            @php $balanced = Money::cmp(Money::normalize($j->total_debit ?? 0), Money::normalize($j->total_credit ?? 0)) === 0; @endphp
                            <tr>
                                <td><input type="checkbox" name="journal_ids[]" value="{{ $j->id }}" class="form-check" x-model="selected"></td>
                                <td><a href="{{ route('journals.show', $j) }}" class="font-mono text-primary hover:underline">{{ $j->ref_no }}</a></td>
                                <td class="whitespace-nowrap">{{ $j->entry_date->translatedFormat('d M Y') }}</td>
                                <td>{{ $j->period->code }} @unless ($j->period->isOpen())<span class="badge bg-error-container/50 text-on-error-container">Closed</span>@endunless</td>
                                <td>{{ $j->source->label() }}@if ($j->transaction)<p class="text-body-sm font-mono text-on-surface-variant">{{ $j->transaction->number }}</p>@endif</td>
                                <td class="max-w-72"><p class="truncate">{{ $j->description }}</p></td>
                                <td class="text-right font-mono whitespace-nowrap">{{ Money::format($j->total_debit ?? 0, false) }}</td>
                                <td class="text-right font-mono whitespace-nowrap">{{ Money::format($j->total_credit ?? 0, false) }}</td>
                                <td class="text-center"><span class="material-symbols-outlined {{ $balanced ? 'text-primary' : 'text-error' }}">{{ $balanced ? 'check_circle' : 'cancel' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="9"><x-empty-state icon="task_alt" title="Tidak ada jurnal Draft" message="Semua jurnal sudah diposting ke General Ledger." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </form>
</x-app-layout>
