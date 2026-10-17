<x-app-layout title="Audit Log">
    <x-page-header eyebrow="Pengaturan" context="Read-only" title="Audit Log" subtitle="Riwayat perubahan data dan status: siapa, kapan, aksi, nilai lama/baru. Log bersifat append-only dan tidak dapat diubah." />

    <form method="GET" class="glass-card p-space-md mb-space-lg grid grid-cols-1 md:grid-cols-5 gap-space-sm items-end">
        <div><label class="form-label" for="user">Pengguna</label>
            <select id="user" name="user" class="form-input"><option value="">Semua</option>
                @foreach ($users as $u)<option value="{{ $u->id }}" @selected((int) request('user') === $u->id)>{{ $u->name }}</option>@endforeach
            </select></div>
        <div><label class="form-label" for="action">Aksi</label>
            <select id="action" name="action" class="form-input"><option value="">Semua</option>
                @foreach ($actions as $a)<option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>@endforeach
            </select></div>
        <div><label class="form-label" for="subject">Objek</label>
            <select id="subject" name="subject" class="form-input"><option value="">Semua</option>
                @foreach ($subjects->unique() as $s)<option value="{{ $s }}" @selected(request('subject') === $s)>{{ $s }}</option>@endforeach
            </select></div>
        <div class="grid grid-cols-2 gap-2">
            <div><label class="form-label" for="from">Dari</label><input id="from" type="date" name="from" value="{{ request('from') }}" class="form-input"></div>
            <div><label class="form-label" for="to">Sampai</label><input id="to" type="date" name="to" value="{{ request('to') }}" class="form-input"></div>
        </div>
        <button class="btn-secondary"><span class="material-symbols-outlined">filter_alt</span> Filter</button>
    </form>

    <section class="glass-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Objek</th><th>Nilai Lama</th><th>Nilai Baru</th><th>Catatan</th><th>IP</th></tr></thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="align-top">
                            <td class="whitespace-nowrap text-body-sm">{{ $log->created_at->translatedFormat('d M Y H:i:s') }}</td>
                            <td class="whitespace-nowrap">{{ $log->user?->name ?? 'Sistem' }}</td>
                            <td><span class="badge bg-secondary-container/60 text-on-secondary-container normal-case font-mono">{{ $log->action }}</span></td>
                            <td class="whitespace-nowrap font-mono text-body-sm">{{ $log->subjectLabel() }}</td>
                            <td class="max-w-64"><pre class="text-[11px] whitespace-pre-wrap break-all text-on-surface-variant">{{ $log->old_values ? json_encode($log->old_values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '—' }}</pre></td>
                            <td class="max-w-64"><pre class="text-[11px] whitespace-pre-wrap break-all">{{ $log->new_values ? json_encode($log->new_values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '—' }}</pre></td>
                            <td class="max-w-56 text-body-sm">{{ $log->note ?: '—' }}</td>
                            <td class="text-body-sm text-on-surface-variant">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty-state icon="history" title="Belum ada log" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-space-md">{{ $logs->links() }}</div>
    </section>
</x-app-layout>
