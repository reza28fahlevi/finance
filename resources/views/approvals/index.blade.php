@php use App\Support\Money; use App\Enums\TransactionType; @endphp
@php $user = auth()->user(); @endphp
<x-app-layout title="Inbox Pending Review">
    <x-page-header eyebrow="Governance & Otorisasi" :context="$user->role->label()" title="Inbox Pending Review"
                   subtitle="Verifikasi dan otorisasi transaksi yang diajukan staf finance sebelum penerbitan jurnal otomatis ke Buku Besar.">
        <x-slot:actions>
            <div class="flex items-center gap-1 p-1 rounded-2xl bg-surface-container/60">
                <a href="{{ route('approvals.index') }}" class="chip chip-active"><span class="material-symbols-outlined text-[18px]!">hourglass_top</span>Menunggu Review ({{ $queue->count() }})</a>
                <a href="{{ route('approvals.history') }}" class="chip bg-transparent!"><span class="material-symbols-outlined text-[18px]!">history</span>Riwayat</a>
            </div>
        </x-slot:actions>
    </x-page-header>

    <section class="glass-card p-space-md mb-space-lg flex items-center gap-space-md flex-wrap">
        <span class="w-11 h-11 rounded-xl bg-secondary-container/50 text-primary flex items-center justify-center"><span class="material-symbols-outlined">shield_person</span></span>
        <div class="flex-1 min-w-60">
            <p class="text-label-lg">Peran Aktif: {{ $user->name }} <span class="ml-1 badge bg-secondary-container text-on-secondary-container normal-case">{{ $user->role->label() }}</span></p>
            <p class="text-body-sm text-on-surface-variant">Prinsip Maker–Checker aktif: Anda tidak dapat menyetujui transaksi buatan sendiri.
                @if ($tiered) Nominal ≥ {{ Money::format($threshold) }} memerlukan otorisasi Manager. @endif</p>
        </div>
    </section>

    <section class="grid grid-cols-1 md:grid-cols-3 gap-space-md mb-space-lg">
        <x-stat-card label="Total Antrean" :value="$queue->count().' Item'" icon="pending_actions">
            @if ($queue->isNotEmpty())<span class="text-primary flex items-center gap-1"><span class="material-symbols-outlined text-[16px]!">priority_high</span>Memerlukan keputusan</span>@endif
        </x-stat-card>
        <x-stat-card label="Akumulasi Nilai Nominal" :value="Money::format($total)" icon="payments" tone="secondary">
            @foreach ($byType as $type => $sum)
                <span>{{ TransactionType::from($type)->label() }}: {{ Money::isPositive($total) ? round((float) bcdiv($sum, $total, 4) * 100, 1) : 0 }}%</span>
            @endforeach
        </x-stat-card>
        <x-stat-card label="Batas Otorisasi Manager" :value="'≥ '.Money::format($threshold)" icon="admin_panel_settings" tone="tertiary"
                     :hint="$tiered ? 'Approval bertingkat aktif' : 'Approval bertingkat nonaktif — Supervisor/Manager dapat menyetujui semua nominal'" />
    </section>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-space-lg items-start">
        {{-- Antrean --}}
        <section class="xl:col-span-5 flex flex-col gap-space-sm">
            <div class="flex items-center justify-between px-1">
                <h2 class="text-label-lg">Daftar Menunggu Persetujuan <span class="ml-1 badge bg-secondary-container text-on-secondary-container">{{ $queue->count() }} antrean</span></h2>
                <div class="flex gap-1">
                    <a href="{{ route('approvals.index') }}" @class(['chip btn-sm', 'chip-active' => request('filter') !== 'large'])>Semua</a>
                    <a href="{{ route('approvals.index', ['filter' => 'large']) }}" @class(['chip btn-sm', 'chip-active' => request('filter') === 'large'])>≥ {{ Money::format($threshold) }}</a>
                </div>
            </div>
            @forelse ($queue as $trx)
                @php $isSelected = $selected && $selected->id === $trx->id; $own = $trx->created_by === $user->id; @endphp
                <a href="{{ route('approvals.index', array_merge(request()->only('filter'), ['id' => $trx->id])) }}"
                   @class(['p-space-md rounded-2xl transition-all flex flex-col gap-space-sm', 'bg-surface-container-high ring-1 ring-primary-container/40' => $isSelected, 'bg-surface-container/60 hover:bg-surface-container-high/70' => ! $isSelected])>
                    <div class="flex items-start justify-between gap-space-sm">
                        <span class="badge bg-secondary-container text-on-secondary-container normal-case">{{ $trx->type->code() }} ({{ $trx->type->label() }})</span>
                        <span class="text-label-sm text-on-surface-variant whitespace-nowrap">{{ $trx->submitted_at?->translatedFormat('d M Y, H:i') }}</span>
                    </div>
                    <div>
                        <p class="text-label-lg text-on-surface">{{ \Illuminate\Support\Str::limit($trx->description, 70) }}</p>
                        <p class="text-body-sm text-on-surface-variant font-mono">{{ $trx->number }}@if ($trx->party_name) • {{ $trx->party_name }}@endif</p>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2 text-body-sm text-on-surface-variant">
                            <span class="w-7 h-7 rounded-full bg-primary-container/80 text-on-primary-container text-label-sm flex items-center justify-center">{{ $trx->creator->initials() }}</span>
                            {{ $trx->creator->name }} (Maker)
                            @if ($own)<span class="badge bg-surface-container-highest text-outline normal-case">Milik Anda</span>@endif
                        </span>
                        <span class="text-label-lg text-primary font-bold">{{ Money::format($trx->amount) }}</span>
                    </div>
                </a>
            @empty
                <div class="glass-card"><x-empty-state icon="task_alt" title="Inbox kosong" message="Tidak ada transaksi yang menunggu review." /></div>
            @endforelse
            <p class="text-body-sm text-on-surface-variant flex items-center gap-1 px-1"><span class="material-symbols-outlined text-[16px]!">verified</span>Maker tidak dapat menyetujui transaksi buatannya sendiri.</p>
        </section>

        {{-- Panel detail --}}
        <section class="xl:col-span-7 glass-card p-space-lg">
            @if ($selected)
                @php $denial = $selected->status->value === 'PENDING_REVIEW' ? $service->denialReason($selected, $user) : 'Transaksi ini tidak sedang menunggu review.'; @endphp
                <div class="flex items-start justify-between gap-space-md mb-space-md">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <span class="badge bg-secondary-container text-on-secondary-container font-mono normal-case">{{ $selected->number }}</span>
                            <span class="badge bg-surface-container-highest text-on-surface-variant normal-case">{{ $selected->type->label() }}</span>
                            <x-status-badge :status="$selected->status" />
                        </div>
                        <h2 class="text-headline-md text-on-surface">{{ $selected->description }}</h2>
                    </div>
                    <a href="{{ route('transactions.show', $selected) }}" class="btn-ghost btn-sm" title="Buka detail transaksi"><span class="material-symbols-outlined">open_in_new</span></a>
                </div>

                <dl class="grid grid-cols-2 md:grid-cols-4 gap-space-md p-space-md glass-panel mb-space-md">
                    <div><dt class="text-label-sm uppercase text-on-surface-variant">Pengaju (Maker)</dt><dd class="mt-1 text-label-md">{{ $selected->creator->name }}</dd><dd class="text-body-sm text-on-surface-variant">{{ $selected->creator->role->label() }}</dd></div>
                    <div><dt class="text-label-sm uppercase text-on-surface-variant">Tanggal</dt><dd class="mt-1 text-label-md">{{ $selected->trx_date->translatedFormat('d M Y') }}</dd><dd class="text-body-sm text-on-surface-variant">Diajukan {{ $selected->submitted_at?->format('H:i') }} WIB</dd></div>
                    <div><dt class="text-label-sm uppercase text-on-surface-variant">{{ $selected->type->partyLabel() }}</dt><dd class="mt-1 text-label-md">{{ $selected->party_name ?: '—' }}</dd></div>
                    <div><dt class="text-label-sm uppercase text-on-surface-variant">Level Otorisasi</dt><dd class="mt-1 text-label-md">{{ $service->requiresManager($selected) ? 'Manager' : 'Supervisor / Manager' }}</dd></div>
                </dl>

                <div class="flex items-center justify-between mb-space-sm">
                    <h3 class="text-label-md uppercase text-on-surface-variant">Proyeksi Jurnal Otomatis (Double Entry)</h3>
                    <span class="text-label-sm text-primary flex items-center gap-1"><span class="material-symbols-outlined text-[16px]!">balance</span>Balance (Debit = Kredit)</span>
                </div>
                <div class="rounded-xl overflow-hidden mb-space-md">
                    <table class="data-table">
                        <thead><tr><th>Kode & Nama Akun</th><th class="text-right">Debit (Rp)</th><th class="text-right">Kredit (Rp)</th></tr></thead>
                        <tbody>
                            <tr><td><p class="text-label-lg">{{ $selected->debitAccount->code }} • {{ $selected->debitAccount->name }}</p></td><td class="text-right">{{ Money::format($selected->amount, false) }}</td><td class="text-right">-</td></tr>
                            <tr><td><p class="text-label-lg">{{ $selected->creditAccount->code }} • {{ $selected->creditAccount->name }}</p></td><td class="text-right">-</td><td class="text-right">{{ Money::format($selected->amount, false) }}</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="glass-panel p-space-md flex items-center justify-between mb-space-md">
                    <span class="text-label-lg">Total yang Diajukan</span>
                    <span class="text-headline-md text-primary-container font-bold">{{ Money::format($selected->amount) }}</span>
                </div>

                <h3 class="text-label-md uppercase text-on-surface-variant mb-space-sm">Lampiran Bukti Pendukung</h3>
                <div class="flex flex-col gap-space-sm mb-space-md">
                    @forelse ($selected->attachments as $att)
                        <div class="glass-panel p-space-sm flex items-center gap-space-sm">
                            <span class="w-10 h-10 rounded-xl bg-secondary-container/50 text-primary flex items-center justify-center"><span class="material-symbols-outlined">{{ $att->isPdf() ? 'picture_as_pdf' : 'image' }}</span></span>
                            <div class="flex-1 min-w-0"><p class="text-label-md truncate">{{ $att->file_name }}</p><p class="text-body-sm text-on-surface-variant">{{ $att->humanSize() }} • SHA-256 {{ substr($att->sha256, 0, 12) }}…</p></div>
                            <a href="{{ $att->downloadUrl(true) }}" target="_blank" class="btn-secondary btn-sm"><span class="material-symbols-outlined text-[16px]!">visibility</span>Lihat</a>
                            <a href="{{ $att->downloadUrl() }}" class="btn-ghost btn-sm" title="Unduh"><span class="material-symbols-outlined">download</span></a>
                        </div>
                    @empty
                        <p class="text-body-sm text-on-surface-variant">Tidak ada lampiran.</p>
                    @endforelse
                </div>

                @can('approval.act')
                    @if ($denial)
                        <div class="p-space-md rounded-xl bg-surface-container-highest/60 text-body-md flex items-start gap-2">
                            <span class="material-symbols-outlined text-primary">block</span>{{ $denial }}
                        </div>
                    @else
                        <div class="glass-panel p-space-md" x-data="{ note: '' }">
                            <label for="note" class="flex items-center justify-between mb-space-sm">
                                <span class="text-label-lg flex items-center gap-2"><span class="material-symbols-outlined text-primary">rate_review</span>Catatan Checker ({{ $user->name }})</span>
                                <span class="text-body-sm text-on-surface-variant">*Wajib diisi bila menolak (Reject)</span>
                            </label>
                            <textarea id="note" x-model="note" rows="2" class="form-input" placeholder="Tuliskan catatan verifikasi (opsional untuk approval, sebutkan alasan jelas jika reject)..."></textarea>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-sm mt-space-md">
                                <form method="POST" action="{{ route('approvals.reject', $selected) }}">@csrf
                                    <input type="hidden" name="note" :value="note">
                                    <button class="btn-secondary w-full py-3" :disabled="note.trim().length < 5" title="Alasan minimal 5 karakter"><span class="material-symbols-outlined">close</span> Tolak Transaksi (Reject)</button>
                                </form>
                                <form method="POST" action="{{ route('approvals.approve', $selected) }}">@csrf
                                    <input type="hidden" name="note" :value="note">
                                    <button class="btn-primary w-full py-3"><span class="material-symbols-outlined">check_circle</span> Setujui & Terbitkan Jurnal</button>
                                </form>
                            </div>
                        </div>
                    @endif
                @endcan
            @else
                <x-empty-state icon="fact_check" title="Pilih transaksi" message="Pilih transaksi di daftar antrean untuk melihat detail dan memberi keputusan." />
            @endif
        </section>
    </div>
</x-app-layout>
