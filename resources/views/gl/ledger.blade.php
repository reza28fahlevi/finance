@php use App\Support\Money; use App\Enums\JournalSource; @endphp
<x-app-layout title="Buku Besar per Akun">
    <x-page-header eyebrow="GL • General Ledger" context="Audit Lock Active" title="Buku Besar per Akun" accent="(General Ledger)"
                   subtitle="Rincian mutasi debit, kredit, dan saldo berjalan akun pada periode terpilih dengan pelacakan jejak audit." />

    <form method="GET" action="{{ route('gl.ledger') }}" class="glass-card p-space-md mb-space-lg grid grid-cols-1 md:grid-cols-3 gap-space-md items-end">
        <div class="md:col-span-2">
            <label class="form-label flex items-center gap-1" for="account"><span class="material-symbols-outlined text-[16px]!">account_tree</span>Pilih Akun Chart of Accounts (COA)</label>
            <select id="account" name="account" class="form-input" onchange="this.form.submit()">
                @foreach ($accounts->groupBy(fn ($a) => $a->type->label()) as $group => $items)
                    <optgroup label="{{ $group }}">
                        @foreach ($items as $a)
                            <option value="{{ $a->id }}" @selected($data && $data['account']->id === $a->id)>{{ $a->code }} • {{ $a->name }}{{ $a->is_active ? '' : ' (nonaktif)' }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label flex items-center gap-1" for="period"><span class="material-symbols-outlined text-[16px]!">calendar_month</span>Periode Akuntansi</label>
            <select id="period" name="period" class="form-input" onchange="this.form.submit()">
                @foreach ($periods as $p)
                    <option value="{{ $p->id }}" @selected($period?->id === $p->id)>{{ $p->code }} — {{ $p->label() }} ({{ $p->status->value }})</option>
                @endforeach
            </select>
        </div>
    </form>

    @if ($data)
        @php $acc = $data['account']; @endphp
        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-space-md mb-space-lg">
            <x-stat-card label="Saldo Awal Pembukaan" :value="Money::format($data['opening'])" icon="lock_open" hint="Carry-forward dari periode sebelumnya" tone="tertiary" />
            <x-stat-card label="Total Mutasi Debit" :value="'+'.Money::format($data['total_debit'])" icon="south" :hint="$data['entries']->where('debit', '>', 0)->count().' entri debit'" />
            <x-stat-card label="Total Mutasi Kredit" :value="'-'.Money::format($data['total_credit'])" icon="north" :hint="$data['entries']->where('credit', '>', 0)->count().' entri kredit'" tone="secondary" />
            <div class="p-space-lg rounded-2xl bg-gradient-to-br from-secondary-container/60 to-surface-container/70 backdrop-blur-xl shadow-xl">
                <div class="flex items-center justify-between"><span class="text-label-sm uppercase tracking-wider text-on-surface-variant">Saldo Akhir Berjalan</span>
                    <span class="badge bg-primary-container text-on-primary-container">{{ $acc->normal_balance->label() }}</span></div>
                <p class="mt-space-sm text-headline-md font-bold">{{ Money::format($data['closing']) }}</p>
                <p class="text-body-sm text-on-surface-variant">Saldo normal: {{ $acc->type->label() }} ({{ $acc->normal_balance->label() }})</p>
            </div>
        </section>

        <section class="glass-card overflow-hidden mb-space-lg">
            <div class="p-space-md flex items-center justify-between gap-space-md">
                <h2 class="text-headline-sm flex items-center gap-2"><span class="material-symbols-outlined text-primary">receipt</span>Running Balance Ledger
                    <span class="badge bg-surface-container-highest text-on-surface-variant">{{ $data['entries']->count() }} entri</span></h2>
                <span class="text-label-md text-on-surface-variant font-mono">{{ $acc->code }} • {{ $acc->name }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Tanggal</th><th>No. Referensi Jurnal</th><th>Sumber Transaksi</th><th>Deskripsi Mutasi</th><th class="text-right">Debit (Rp)</th><th class="text-right">Kredit (Rp)</th><th class="text-right">Saldo (Rp)</th></tr></thead>
                    <tbody>
                        <tr>
                            <td class="font-mono whitespace-nowrap">{{ $data['period']->start_date->translatedFormat('d M Y') }}</td>
                            <td>-</td>
                            <td><span class="badge bg-surface-container-highest text-on-surface-variant">SYSTEM_BF</span></td>
                            <td class="text-label-md">Saldo Awal Pembukuan Periode</td>
                            <td class="text-right">-</td><td class="text-right">-</td>
                            <td class="text-right font-mono font-semibold">{{ Money::format($data['opening'], false) }}</td>
                        </tr>
                        @foreach ($data['entries'] as $e)
                            <tr>
                                <td class="font-mono whitespace-nowrap">{{ $e->entry_date->translatedFormat('d M Y') }}</td>
                                <td><a href="{{ route('journals.show', $e->journal_id) }}" class="font-mono text-label-lg text-primary hover:underline">{{ $e->journal->ref_no }}</a></td>
                                <td>
                                    @if ($e->journal->transaction)
                                        <span class="badge bg-secondary-container/60 text-on-secondary-container normal-case whitespace-nowrap">{{ $e->journal->transaction->type->code() }} - {{ $e->journal->transaction->number }}</span>
                                    @else
                                        <span class="badge bg-surface-container-highest text-on-surface-variant">{{ $e->journal->source === JournalSource::Auto ? 'AUTO' : 'MANUAL' }}</span>
                                    @endif
                                </td>
                                <td class="max-w-80 text-body-sm">{{ $e->description }}</td>
                                <td class="text-right font-mono">{{ Money::isPositive($e->debit) ? Money::format($e->debit, false) : '-' }}</td>
                                <td class="text-right font-mono">{{ Money::isPositive($e->credit) ? Money::format($e->credit, false) : '-' }}</td>
                                <td class="text-right font-mono font-semibold">{{ Money::format($e->running_balance, false) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-surface-container-highest/50">
                            <td colspan="4" class="px-4 py-3 text-right text-label-md uppercase">Total Mutasi Periode Ini & Saldo Akhir:</td>
                            <td class="px-4 py-3 text-right font-mono text-primary">{{ Money::format($data['total_debit'], false) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-primary">{{ Money::format($data['total_credit'], false) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-headline-sm">{{ Money::format($data['closing']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        @php
            $mutTotal = Money::add($data['total_debit'], $data['total_credit']);
            $debitPct = Money::isPositive($mutTotal) ? round((float) bcdiv($data['total_debit'], $mutTotal, 6) * 100, 2) : 0;
        @endphp
        <section class="glass-card p-space-lg">
            <div class="flex items-center justify-between mb-space-md"><h2 class="text-headline-sm">Komposisi Mutasi Akun Ini</h2><span class="text-label-sm text-on-surface-variant">Debit Ratio: {{ $debitPct }}%</span></div>
            <div class="h-3 rounded-full bg-surface-container-lowest overflow-hidden flex">
                <div class="h-full bg-primary-container" style="width: {{ $debitPct }}%"></div>
                <div class="h-full bg-secondary-container" style="width: {{ Money::isPositive($mutTotal) ? 100 - $debitPct : 0 }}%"></div>
            </div>
            <div class="mt-space-sm flex gap-space-lg text-label-md">
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-primary-container"></span>Mutasi Debit: {{ Money::format($data['total_debit']) }}</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-secondary-container"></span>Mutasi Kredit: {{ Money::format($data['total_credit']) }}</span>
            </div>
        </section>
    @else
        <div class="glass-card"><x-empty-state icon="account_balance" title="Pilih akun dan periode" message="Belum ada akun postable atau periode akuntansi." /></div>
    @endif
</x-app-layout>
