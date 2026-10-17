@php use App\Support\Money; use App\Enums\JournalStatus; use App\Enums\JournalSource; @endphp
<x-app-layout title="Daftar Jurnal">
    <x-page-header eyebrow="Modul Akuntansi" context="Journal Entry" title="Daftar Jurnal"
                   subtitle="Semua jurnal otomatis (dari transaksi) dan manual. Jurnal POSTED bersifat immutable — koreksi melalui jurnal pembalik.">
        <x-slot:actions>
            @can('journal.manage')
                <a href="{{ route('journals.batch') }}" class="btn-secondary"><span class="material-symbols-outlined text-primary">auto_read_play</span> Posting Batch</a>
                <a href="{{ route('journals.create') }}" class="btn-primary"><span class="material-symbols-outlined">add_circle</span> Jurnal Manual</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <section class="glass-card p-space-md mb-space-lg flex flex-col gap-space-md">
        <div class="flex items-center gap-space-sm flex-wrap">
            <a href="{{ route('journals.index', request()->except(['status', 'page'])) }}" @class(['chip', 'chip-active' => ! request('status')])>Semua ({{ $counts->sum() }})</a>
            @foreach (JournalStatus::cases() as $s)
                <a href="{{ route('journals.index', array_merge(request()->except('page'), ['status' => $s->value])) }}" @class(['chip', 'chip-active' => request('status') === $s->value])>{{ ucfirst(strtolower($s->value)) }} ({{ $counts[$s->value] ?? 0 }})</a>
            @endforeach
        </div>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-space-sm items-end">
            @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            <div class="md:col-span-2"><label class="form-label" for="q">Cari</label><input id="q" type="search" name="q" value="{{ request('q') }}" class="form-input" placeholder="No. referensi atau deskripsi..."></div>
            <div><label class="form-label" for="source">Sumber</label>
                <select id="source" name="source" class="form-input"><option value="">Semua Sumber</option>
                    @foreach (JournalSource::cases() as $src)<option value="{{ $src->value }}" @selected(request('source') === $src->value)>{{ $src->label() }}</option>@endforeach
                </select></div>
            <div class="flex gap-2"><div class="flex-1"><label class="form-label" for="period">Periode</label>
                <select id="period" name="period" class="form-input"><option value="">Semua Periode</option>
                    @foreach ($periods as $p)<option value="{{ $p->id }}" @selected((int) request('period') === $p->id)>{{ $p->code }}</option>@endforeach
                </select></div>
                <button class="btn-secondary self-end"><span class="material-symbols-outlined">filter_alt</span></button></div>
        </form>
    </section>

    <section class="glass-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>No. Referensi</th><th>Tanggal</th><th>Periode</th><th>Sumber</th><th>Deskripsi</th><th class="text-right">Total</th><th class="text-center">Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($journals as $j)
                        <tr>
                            <td class="whitespace-nowrap"><a href="{{ route('journals.show', $j) }}" class="font-mono text-label-lg text-primary hover:underline">{{ $j->ref_no }}</a></td>
                            <td class="whitespace-nowrap">{{ $j->entry_date->translatedFormat('d M Y') }}</td>
                            <td>{{ $j->period->code }}</td>
                            <td><span class="badge {{ $j->source === JournalSource::Auto ? 'bg-primary-container/15 text-primary' : 'bg-surface-container-highest text-on-surface-variant' }}">{{ $j->source->label() }}</span>
                                @if ($j->transaction)<p class="text-body-sm font-mono text-on-surface-variant mt-0.5">{{ $j->transaction->number }}</p>@endif
                                @if ($j->reversal_of_id)<p class="text-body-sm text-on-surface-variant mt-0.5">Pembalik</p>@endif</td>
                            <td class="max-w-80"><p class="truncate">{{ $j->description }}</p><p class="text-body-sm text-on-surface-variant">{{ $j->creator->name }}</p></td>
                            <td class="text-right whitespace-nowrap font-semibold">{{ Money::format($j->total_debit ?? 0) }}</td>
                            <td class="text-center"><x-status-badge :status="$j->status" /></td>
                            <td class="text-right whitespace-nowrap">
                                @if ($j->status === JournalStatus::Draft)
                                    @can('journal.manage')
                                        <form method="POST" action="{{ route('journals.post', $j) }}" class="inline">@csrf<button class="btn-primary btn-sm">Post</button></form>
                                    @endcan
                                @endif
                                <a href="{{ route('journals.show', $j) }}" class="btn-ghost btn-sm"><span class="material-symbols-outlined text-[18px]!">visibility</span></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty-state icon="menu_book" title="Belum ada jurnal" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-space-md">{{ $journals->links() }}</div>
    </section>
</x-app-layout>
