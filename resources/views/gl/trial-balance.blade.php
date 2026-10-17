@php use App\Support\Money; @endphp
<x-app-layout title="Trial Balance">
    <x-page-header eyebrow="General Ledger" context="Neraca Saldo" title="Trial Balance"
                   :subtitle="'Neraca saldo periode '.($period?->code ?? '—').': memastikan total debit = total kredit seluruh akun.'" />

    @include('gl._period-picker', ['route' => 'gl.trial-balance'])

    @if ($tb)
        <section class="glass-card p-space-lg mb-space-lg flex items-center gap-space-md flex-wrap {{ $tb['balanced'] ? '' : 'ring-1 ring-error/40' }}">
            <span class="w-14 h-14 rounded-2xl flex items-center justify-center {{ $tb['balanced'] ? 'bg-primary-container/25 text-primary' : 'bg-error-container/40 text-error' }}">
                <span class="material-symbols-outlined text-[28px]!">{{ $tb['balanced'] ? 'verified' : 'warning' }}</span>
            </span>
            <div class="flex-1">
                <h2 class="text-headline-sm">{{ $tb['balanced'] ? 'Trial Balance Seimbang' : 'Trial Balance TIDAK Seimbang' }}</h2>
                <p class="text-body-sm text-on-surface-variant">Total saldo debit {{ Money::format($tb['total_debit']) }} {{ $tb['balanced'] ? '=' : '≠' }} total saldo kredit {{ Money::format($tb['total_credit']) }}.
                    Mutasi periode: D {{ Money::format($tb['mutation_debit']) }} / K {{ Money::format($tb['mutation_credit']) }}.</p>
            </div>
        </section>

        <section class="glass-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Kode</th><th>Nama Akun</th><th>Tipe</th><th class="text-right">Debit (Rp)</th><th class="text-right">Kredit (Rp)</th></tr></thead>
                    <tbody>
                        @forelse ($tb['rows'] as $r)
                            <tr>
                                <td class="font-mono"><a href="{{ route('gl.ledger', ['account' => $r['account']->id, 'period' => $period->id]) }}" class="text-primary hover:underline">{{ $r['account']->code }}</a></td>
                                <td>{{ $r['account']->name }}</td>
                                <td class="text-body-sm">{{ $r['account']->type->label() }}</td>
                                <td class="text-right font-mono">{{ Money::isPositive($r['debit']) ? Money::format($r['debit'], false) : '' }}</td>
                                <td class="text-right font-mono">{{ Money::isPositive($r['credit']) ? Money::format($r['credit'], false) : '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty-state icon="balance" title="Belum ada saldo pada periode ini" /></td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-surface-container-highest/50">
                            <td colspan="3" class="px-4 py-3 text-right text-label-md uppercase">Total</td>
                            <td class="px-4 py-3 text-right font-mono font-bold {{ $tb['balanced'] ? 'text-primary' : 'text-error' }}">{{ Money::format($tb['total_debit'], false) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold {{ $tb['balanced'] ? 'text-primary' : 'text-error' }}">{{ Money::format($tb['total_credit'], false) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    @else
        <div class="glass-card"><x-empty-state icon="event_busy" title="Belum ada periode" /></div>
    @endif
</x-app-layout>
