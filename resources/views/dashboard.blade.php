@php use App\Support\Money; use App\Enums\TransactionType; @endphp
<x-app-layout title="Dashboard">
    <x-page-header eyebrow="Portal Eksekutif" :context="$user->role->label()"
                   :title="'Selamat Datang, '.\Illuminate\Support\Str::of($user->name)->before(',')">
        <x-slot:subtitle>
            Ringkasan finansial periode berjalan:
            <span class="text-on-surface font-semibold">{{ $period?->label() ?? '—' }}</span>
            @if ($period)
                <x-status-badge :status="$period->status" class="ml-1" />
            @endif
        </x-slot:subtitle>
        <x-slot:actions>
            @can('transaction.manage')
                <a href="{{ route('transactions.create', TransactionType::CashOut->slug()) }}" class="btn-primary">
                    <span class="material-symbols-outlined">add</span> Input Transaksi Baru
                </a>
            @endcan
            @can('journal.manage')
                <a href="{{ route('journals.batch') }}" class="btn-secondary">
                    <span class="material-symbols-outlined text-primary">auto_read_play</span> Posting Batch Jurnal
                    @if ($draftJournalsAll > 0)
                        <span class="px-1.5 py-0.5 rounded-full bg-secondary-container text-on-secondary-container text-label-sm">{{ $draftJournalsAll }} antrean</span>
                    @endif
                </a>
            @endcan
            @can('approval.act')
                <a href="{{ route('approvals.index') }}" class="btn-secondary">
                    <span class="material-symbols-outlined text-primary">fact_check</span> Buka Inbox Review
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- KPI --}}
    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-space-md mb-space-xl">
        <x-stat-card label="Saldo Kas & Bank" :value="Money::format($cashTotal)" icon="account_balance_wallet">
            <span class="text-primary flex items-center gap-1"><span class="material-symbols-outlined text-[16px]!">south_west</span>Masuk {{ Money::format($cashIn) }}</span>
            <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px]!">north_east</span>Keluar {{ Money::format($cashOut) }}</span>
        </x-stat-card>
        <x-stat-card label="Pending Review" :value="$pendingCount.' Transaksi'" icon="pending_actions" tone="secondary">
            Total nilai: <span class="text-on-surface font-semibold">{{ Money::format($pendingTotal) }}</span>
        </x-stat-card>
        <x-stat-card label="Jurnal Belum Diposting" :value="$draftJournals.' Jurnal'" icon="library_books" tone="tertiary">
            <span class="w-1.5 h-1.5 rounded-full bg-tertiary"></span> Periode {{ $period?->code ?? '—' }} · total semua: {{ $draftJournalsAll }}
        </x-stat-card>
        <x-stat-card label="Mutasi Periode Ini" :value="Money::format($mutationDebit)" icon="balance">
            <span class="truncate">D: {{ Money::format($mutationDebit, false) }} | K: {{ Money::format($mutationCredit, false) }}</span>
            @if (Money::cmp($mutationDebit, $mutationCredit) === 0)
                <span class="text-primary flex items-center gap-0.5 font-semibold"><span class="material-symbols-outlined text-[14px]!">check_circle</span>Balance</span>
            @else
                <span class="text-error font-semibold">Tidak seimbang!</span>
            @endif
        </x-stat-card>
    </section>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-space-lg">
        {{-- Kiri: antrean approval / draft saya --}}
        <section class="xl:col-span-7 flex flex-col gap-space-lg">
            <div class="glass-card p-space-lg">
                <x-card-title :title="$user->isChecker() ? 'Menunggu Persetujuan Anda' : 'Transaksi Menunggu Approval'"
                              subtitle="Pemeriksaan transaksi sebelum masuk tahap penjurnalan otomatis">
                    <x-slot:actions>
                        <span class="px-2 py-0.5 rounded-full bg-secondary-container text-on-secondary-container text-label-sm">{{ $pendingCount }}</span>
                    </x-slot:actions>
                </x-card-title>

                <div class="flex flex-col gap-space-sm">
                    @forelse ($pendingList as $trx)
                        <div class="p-space-md glass-panel hover:bg-surface-container-high transition-all flex flex-col md:flex-row md:items-center justify-between gap-space-md">
                            <div class="flex items-start gap-space-sm min-w-0 flex-1">
                                <span class="w-10 h-10 rounded-xl bg-secondary-container/50 text-secondary flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined">{{ $trx->type->icon() }}</span>
                                </span>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-space-xs flex-wrap">
                                        <span class="text-label-sm text-secondary uppercase tracking-wide">{{ $trx->type->label() }}</span>
                                        <span class="text-on-surface-variant text-[12px]">•</span>
                                        <span class="text-label-sm text-on-surface-variant font-mono">{{ $trx->number }}</span>
                                    </div>
                                    <h3 class="text-label-lg text-on-surface truncate mt-0.5">{{ \Illuminate\Support\Str::limit($trx->description, 60) }}</h3>
                                    <div class="flex items-center gap-space-sm mt-1 text-on-surface-variant text-body-sm flex-wrap">
                                        <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[15px]!">person</span>{{ $trx->creator->name }}</span>
                                        @if ($trx->attachments->isNotEmpty())
                                            <span class="flex items-center gap-1 text-primary"><span class="material-symbols-outlined text-[15px]!">attach_file</span>{{ $trx->attachments->count() }} lampiran</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between md:flex-col md:items-end gap-space-sm shrink-0">
                                <span class="text-headline-sm font-bold text-on-surface">{{ Money::format($trx->amount) }}</span>
                                <a href="{{ auth()->user()->can('approval.view') ? route('approvals.index', ['id' => $trx->id]) : route('transactions.show', $trx) }}" class="btn-primary btn-sm">Detail</a>
                            </div>
                        </div>
                    @empty
                        <x-empty-state icon="task_alt" title="Tidak ada antrean" message="Semua transaksi sudah diproses." />
                    @endforelse
                </div>

                @can('approval.view')
                    <div class="mt-space-md pt-space-sm flex items-center justify-between text-on-surface-variant text-label-sm">
                        <span>Menampilkan {{ $pendingList->count() }} dari {{ $pendingCount }} antrean</span>
                        <a href="{{ route('approvals.index') }}" class="text-primary hover:underline flex items-center gap-1">Buka Inbox Review Lengkap <span class="material-symbols-outlined text-[16px]!">arrow_forward</span></a>
                    </div>
                @endcan
            </div>

            @if ($myDrafts->isNotEmpty())
                <div class="glass-card p-space-lg">
                    <x-card-title title="Draft & Revisi Saya" subtitle="Transaksi yang belum Anda submit atau dikembalikan oleh reviewer" />
                    <div class="flex flex-col gap-space-sm">
                        @foreach ($myDrafts as $trx)
                            <a href="{{ route('transactions.show', $trx) }}" class="p-space-md glass-panel hover:bg-surface-container-high transition flex items-center justify-between gap-space-md">
                                <div class="min-w-0">
                                    <span class="text-label-sm font-mono text-on-surface-variant">{{ $trx->number }}</span>
                                    <p class="text-label-lg truncate">{{ $trx->description }}</p>
                                </div>
                                <div class="flex items-center gap-space-sm shrink-0">
                                    <span class="text-label-lg">{{ Money::format($trx->amount) }}</span>
                                    <x-status-badge :status="$trx->status" />
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>

        {{-- Kanan: akun kas & status periode --}}
        <section class="xl:col-span-5 flex flex-col gap-space-lg">
            <div class="glass-card p-space-lg flex flex-col gap-space-md">
                <x-card-title title="Status Akun Kas" subtitle="Likuiditas akun kas/bank pada periode berjalan" class="mb-0!" />

                @php
                    $positiveTotal = $cashRows->reduce(fn ($s, $r) => Money::cmp($r['balance'], '0') > 0 ? Money::add($s, $r['balance']) : $s, '0.00');
                    $colors = ['bg-primary-container', 'bg-secondary', 'bg-tertiary', 'bg-outline'];
                @endphp
                @if (Money::isPositive($positiveTotal))
                    <div class="w-full flex flex-col gap-2">
                        <div class="h-2.5 w-full rounded-full bg-surface-container-lowest flex overflow-hidden p-0.5">
                            @foreach ($cashRows as $i => $row)
                                @if (Money::cmp($row['balance'], '0') > 0)
                                    <div class="h-full {{ $colors[$i % 4] }} first:rounded-l-full last:rounded-r-full" style="width: {{ round((float) bcdiv($row['balance'], $positiveTotal, 6) * 100, 2) }}%"></div>
                                @endif
                            @endforeach
                        </div>
                        <div class="flex items-center gap-x-space-md gap-y-1 flex-wrap text-label-sm text-on-surface-variant">
                            @foreach ($cashRows as $i => $row)
                                @if (Money::cmp($row['balance'], '0') > 0)
                                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full {{ $colors[$i % 4] }}"></span>{{ $row['account']->name }} ({{ round((float) bcdiv($row['balance'], $positiveTotal, 6) * 100) }}%)</span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                @forelse ($cashRows as $row)
                    <div class="p-space-md rounded-xl bg-surface-container-high/40 flex items-center justify-between gap-space-sm hover:bg-surface-container-high/70 transition-colors">
                        <div class="flex items-center gap-space-sm min-w-0">
                            <span class="w-10 h-10 rounded-xl bg-surface-container-highest flex items-center justify-center text-label-md text-primary shrink-0">
                                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(preg_replace('/^(Kas|Bank)\s*/i', '', $row['account']->name) ?: $row['account']->name, 0, 3)) }}
                            </span>
                            <div class="min-w-0">
                                <p class="text-label-lg text-on-surface truncate">{{ $row['account']->name }}</p>
                                <p class="text-body-sm text-on-surface-variant font-mono">{{ $row['account']->code }} • Normal: {{ $row['account']->normal_balance->label() }}</p>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-label-lg text-on-surface font-bold">{{ Money::format($row['balance']) }}</p>
                            @can('gl.view')
                                <a href="{{ route('gl.ledger', ['account' => $row['account']->id, 'period' => $period?->id]) }}" class="text-label-sm text-primary hover:underline">Buku besar →</a>
                            @endcan
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="account_balance" title="Belum ada akun kas" message="Tandai akun kas/bank di Chart of Accounts." />
                @endforelse
            </div>

            <div class="p-space-lg rounded-2xl bg-surface-container-low/80 backdrop-blur-2xl shadow-xl">
                <div class="flex items-start justify-between gap-space-sm">
                    <div>
                        <div class="flex items-center gap-space-xs mb-1">
                            <span class="material-symbols-outlined text-[18px]! text-primary">event_available</span>
                            <h3 class="text-label-lg text-on-surface">Periode Akuntansi Aktif</h3>
                        </div>
                        <p class="text-headline-sm font-bold text-on-surface">{{ $period ? $period->code.' ('.($period->isOpen() ? 'Terbuka' : 'Tertutup').')' : 'Belum ada periode' }}</p>
                    </div>
                    <span class="px-2.5 py-1 rounded-full bg-secondary-container/60 text-secondary text-label-sm">{{ $user->role->label() }}</span>
                </div>
                @if ($period)
                    <dl class="mt-space-md pt-space-sm space-y-space-xs text-body-sm">
                        <div class="flex items-center justify-between"><dt class="text-on-surface-variant">Rentang Tanggal</dt><dd class="text-on-surface font-semibold">{{ $period->start_date->translatedFormat('d M') }} – {{ $period->end_date->translatedFormat('d M Y') }}</dd></div>
                        @if ($period->isOpen())
                            <div class="flex items-center justify-between"><dt class="text-on-surface-variant">Sisa Masa Aktif Transaksi</dt><dd class="text-primary font-bold">{{ max(0, (int) now()->startOfDay()->diffInDays($period->end_date, false)) }} hari tersisa</dd></div>
                        @endif
                        <div class="flex items-center justify-between"><dt class="text-on-surface-variant">Jurnal Belum Diposting</dt><dd class="text-on-surface font-semibold">{{ $draftJournals }}</dd></div>
                        <div class="flex items-center justify-between"><dt class="text-on-surface-variant">Integritas Posting Buku Besar</dt>
                            <dd class="flex items-center gap-1 font-semibold {{ Money::cmp($mutationDebit, $mutationCredit) === 0 ? 'text-primary' : 'text-error' }}">
                                <span class="material-symbols-outlined text-[15px]!">{{ Money::cmp($mutationDebit, $mutationCredit) === 0 ? 'verified' : 'warning' }}</span>
                                {{ Money::cmp($mutationDebit, $mutationCredit) === 0 ? 'Terverifikasi Seimbang' : 'Tidak Seimbang' }}
                            </dd>
                        </div>
                    </dl>
                @endif
            </div>
        </section>
    </div>
</x-app-layout>
