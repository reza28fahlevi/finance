@php use App\Support\Money; @endphp
<x-app-layout title="Riwayat Approval">
    <x-page-header eyebrow="Governance & Otorisasi" title="Riwayat Approval" subtitle="Log keputusan yang sudah diambil: siapa, kapan, aksi, dan catatan.">
        <x-slot:actions>
            <a href="{{ route('approvals.index') }}" class="btn-secondary"><span class="material-symbols-outlined">inbox</span> Inbox Pending Review</a>
        </x-slot:actions>
    </x-page-header>

    <section class="glass-card p-space-md mb-space-lg flex items-center gap-space-sm flex-wrap">
        <a href="{{ route('approvals.history') }}" @class(['chip', 'chip-active' => ! request('action') && ! request('mine')])>Semua ({{ $counts->sum() }})</a>
        <a href="{{ route('approvals.history', ['action' => 'APPROVE']) }}" @class(['chip', 'chip-active' => request('action') === 'APPROVE'])>Disetujui ({{ $counts['APPROVE'] ?? 0 }})</a>
        <a href="{{ route('approvals.history', ['action' => 'REJECT']) }}" @class(['chip', 'chip-active' => request('action') === 'REJECT'])>Ditolak ({{ $counts['REJECT'] ?? 0 }})</a>
        @can('approval.act')
            <a href="{{ route('approvals.history', ['mine' => 1]) }}" @class(['chip', 'chip-active' => request('mine')])>Keputusan Saya</a>
        @endcan
    </section>

    <section class="glass-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Waktu</th><th>Transaksi</th><th>Jenis</th><th class="text-right">Nominal</th><th class="text-center">Aksi</th><th>Oleh</th><th>Catatan</th></tr></thead>
                <tbody>
                    @forelse ($approvals as $ap)
                        <tr>
                            <td class="whitespace-nowrap">{{ $ap->acted_at->translatedFormat('d M Y H:i') }}</td>
                            <td><a href="{{ route('transactions.show', $ap->transaction) }}" class="font-mono text-primary hover:underline">{{ $ap->transaction->number }}</a>
                                <p class="text-body-sm text-on-surface-variant truncate max-w-64">{{ $ap->transaction->description }}</p></td>
                            <td class="whitespace-nowrap">{{ $ap->transaction->type->label() }}</td>
                            <td class="text-right whitespace-nowrap">{{ Money::format($ap->transaction->amount) }}</td>
                            <td class="text-center">
                                <span class="badge badge-dot {{ $ap->action->value === 'APPROVE' ? 'bg-success-container/60 text-success' : 'bg-error-container/60 text-on-error-container' }}">{{ $ap->action->value === 'APPROVE' ? 'Approve' : 'Reject' }}</span>
                            </td>
                            <td class="whitespace-nowrap">{{ $ap->approver->name }}<p class="text-body-sm text-on-surface-variant">{{ $ap->approver->role->label() }}</p></td>
                            <td class="max-w-80 text-body-sm">{{ $ap->note ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="history" title="Belum ada riwayat approval" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-space-md">{{ $approvals->links() }}</div>
    </section>
</x-app-layout>
