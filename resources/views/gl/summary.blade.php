@php use App\Support\Money; @endphp
<x-app-layout title="Ringkasan Saldo Akun">
    <x-page-header eyebrow="General Ledger" title="Ringkasan Saldo Akun"
                   :subtitle="'Saldo awal, total debit, total kredit, dan saldo akhir seluruh akun postable — periode '.($period?->code ?? '—').'.'" />

    @include('gl._period-picker', ['route' => 'gl.summary', 'extra' => request()->boolean('hide_empty') ? ['hide_empty' => 1] : []])

    @php
        $sum = fn ($key) => $rows->reduce(fn ($s, $r) => Money::add($s, $r[$key]), '0.00');
        $td = $sum('total_debit'); $tc = $sum('total_credit');
    @endphp
    <section class="grid grid-cols-1 md:grid-cols-3 gap-space-md mb-space-lg">
        <x-stat-card label="Total Mutasi Debit" :value="Money::format($td)" icon="south" />
        <x-stat-card label="Total Mutasi Kredit" :value="Money::format($tc)" icon="north" tone="secondary" />
        <x-stat-card label="Integritas Mutasi" :value="Money::cmp($td, $tc) === 0 ? 'Seimbang' : 'Tidak Seimbang'" icon="balance" tone="tertiary" :hint="'Selisih: '.Money::format(Money::abs(Money::sub($td, $tc)))" />
    </section>

    <section class="glass-card overflow-hidden">
        <div class="p-space-md flex items-center justify-between">
            <h2 class="text-headline-sm">Saldo per Akun</h2>
            <a href="{{ route('gl.summary', array_merge(request()->query(), ['hide_empty' => request()->boolean('hide_empty') ? 0 : 1])) }}" class="chip {{ request()->boolean('hide_empty') ? 'chip-active' : '' }}">Sembunyikan akun tanpa saldo</a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Kode</th><th>Nama Akun</th><th>Tipe</th><th>Normal</th><th class="text-right">Saldo Awal</th><th class="text-right">Total Debit</th><th class="text-right">Total Kredit</th><th class="text-right">Saldo Akhir</th></tr></thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td class="font-mono"><a href="{{ route('gl.ledger', ['account' => $r['account']->id, 'period' => $period->id]) }}" class="text-primary hover:underline">{{ $r['account']->code }}</a></td>
                            <td>{{ $r['account']->name }}</td>
                            <td class="text-body-sm">{{ $r['account']->type->label() }}</td>
                            <td class="text-body-sm">{{ $r['account']->normal_balance->label() }}</td>
                            <td class="text-right font-mono">{{ Money::format($r['opening'], false) }}</td>
                            <td class="text-right font-mono">{{ Money::format($r['total_debit'], false) }}</td>
                            <td class="text-right font-mono">{{ Money::format($r['total_credit'], false) }}</td>
                            <td class="text-right font-mono font-semibold">{{ Money::format($r['closing'], false) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty-state icon="account_balance" title="Belum ada saldo" /></td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-surface-container-highest/50">
                        <td colspan="5" class="px-4 py-3 text-right text-label-md uppercase">Total Mutasi</td>
                        <td class="px-4 py-3 text-right font-mono font-bold">{{ Money::format($td, false) }}</td>
                        <td class="px-4 py-3 text-right font-mono font-bold">{{ Money::format($tc, false) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</x-app-layout>
