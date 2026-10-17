@php use App\Support\Money; use App\Enums\TransactionStatus; use App\Enums\TransactionType; @endphp
<x-app-layout title="Semua Transaksi">
    <x-page-header eyebrow="Buku Kas & Transaksi Operasional" title="Semua Transaksi"
                   subtitle="Daftar transaksi operasional masuk, keluar, dan faktur dalam alur pembukuan.">
        <x-slot:actions>
            <a href="{{ route('transactions.export', request()->query()) }}" class="btn-secondary"><span class="material-symbols-outlined">download</span> Ekspor CSV</a>
            @can('create', App\Models\Transaction::class)
                <x-dropdown align="right" width="56">
                    <x-slot name="trigger">
                        <button type="button" class="btn-primary"><span class="material-symbols-outlined">add_circle</span> Input Transaksi Baru</button>
                    </x-slot>
                    <x-slot name="content">
                        @foreach (TransactionType::cases() as $t)
                            <x-dropdown-link :href="route('transactions.create', $t->slug())"><span class="material-symbols-outlined text-[18px]!">{{ $t->icon() }}</span>{{ $t->label() }}</x-dropdown-link>
                        @endforeach
                    </x-slot>
                </x-dropdown>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-space-md mb-space-lg">
        <x-stat-card label="Total Kas Masuk" :value="Money::format($kpi['cash_in'])" icon="south_west" :hint="$kpi['cash_in_count'].' transaksi terverifikasi'" />
        <x-stat-card label="Total Kas Keluar" :value="Money::format($kpi['cash_out'])" icon="north_east" :hint="$kpi['cash_out_count'].' beban & pengeluaran'" tone="tertiary" />
        <x-stat-card label="Menunggu Review" :value="$kpi['pending'].' Voucher'" icon="pending_actions" :hint="'Nominal: '.Money::format($kpi['pending_amount'])" tone="secondary" />
        <x-stat-card label="Telah Dijurnal" :value="$kpi['journaled'].' Transaksi'" icon="task_alt" hint="Jurnal otomatis telah terbit" />
    </section>

    <section class="glass-card p-space-md mb-space-lg flex flex-col gap-space-md">
        @php $total = $statusCounts->sum(); @endphp
        <div class="flex items-center gap-space-sm flex-wrap">
            <a href="{{ route('transactions.index', request()->except(['status', 'page'])) }}" @class(['chip', 'chip-active' => ! request('status')])>Semua ({{ $total }})</a>
            @foreach (TransactionStatus::cases() as $s)
                <a href="{{ route('transactions.index', array_merge(request()->except('page'), ['status' => $s->value])) }}" @class(['chip', 'chip-active' => request('status') === $s->value])>
                    {{ $s->label() }} <span class="px-1.5 rounded-full bg-surface-container-highest/80 text-label-sm">{{ $statusCounts[$s->value] ?? 0 }}</span>
                </a>
            @endforeach
        </div>

        <form method="GET" class="flex flex-wrap gap-space-sm items-end">
            @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            <div class="flex-1 min-w-64">
                <label class="form-label" for="q">Cari</label>
                <input id="q" type="search" name="q" value="{{ request('q') }}" class="form-input" placeholder="No. transaksi, deskripsi, pihak, referensi...">
            </div>
            <div class="w-44">
                <label class="form-label" for="type">Jenis</label>
                <select id="type" name="type" class="form-input">
                    <option value="">Semua Tipe</option>
                    @foreach (TransactionType::cases() as $t)
                        <option value="{{ $t->value }}" @selected(request('type') === $t->value)>{{ $t->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-44">
                <label class="form-label" for="period">Periode</label>
                <select id="period" name="period" class="form-input">
                    <option value="">Semua Periode</option>
                    @foreach ($periods as $p)
                        <option value="{{ $p->id }}" @selected((int) request('period') === $p->id)>{{ $p->code }} ({{ $p->status->value }})</option>
                    @endforeach
                </select>
            </div>
            <div class="w-40"><label class="form-label" for="from">Dari</label><input id="from" type="date" name="from" value="{{ request('from') }}" class="form-input"></div>
            <div class="w-40"><label class="form-label" for="to">Sampai</label><input id="to" type="date" name="to" value="{{ request('to') }}" class="form-input"></div>
            <div class="flex gap-2">
                <select name="sort" class="form-input w-48" aria-label="Urutkan">
                    <option value="">Tanggal Terbaru</option>
                    <option value="oldest" @selected(request('sort') === 'oldest')>Tanggal Terlama</option>
                    <option value="amount_desc" @selected(request('sort') === 'amount_desc')>Nominal Terbesar</option>
                    <option value="amount_asc" @selected(request('sort') === 'amount_asc')>Nominal Terkecil</option>
                </select>
                <button class="btn-secondary"><span class="material-symbols-outlined">filter_alt</span></button>
            </div>
        </form>
    </section>

    <section class="glass-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr><th>No. Transaksi</th><th>Tanggal</th><th>Jenis Transaksi</th><th>Pihak Terkait</th><th>Deskripsi Singkat</th><th class="text-right">Nominal</th><th class="text-center">Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $trx)
                        <tr>
                            <td class="whitespace-nowrap">
                                <a href="{{ route('transactions.show', $trx) }}" class="font-mono text-label-lg text-primary hover:underline">{{ $trx->number }}</a>
                                <p class="text-body-sm text-on-surface-variant">{{ $trx->reference ? 'Ref: '.$trx->reference : 'Tanpa Referensi' }}</p>
                            </td>
                            <td class="whitespace-nowrap">
                                {{ $trx->trx_date->translatedFormat('d M Y') }}
                                <p class="text-body-sm text-on-surface-variant">{{ $trx->created_at->format('H:i') }} WIB</p>
                            </td>
                            <td><span class="badge bg-surface-container-highest text-on-surface normal-case whitespace-nowrap"><span class="material-symbols-outlined text-[14px]!">{{ $trx->type->icon() }}</span>{{ $trx->type->label() }}</span></td>
                            <td>
                                @if ($trx->party_name)
                                    <span class="flex items-center gap-2"><span class="w-7 h-7 rounded-full bg-surface-container-highest text-label-sm flex items-center justify-center shrink-0">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($trx->party_name, 0, 2)) }}</span><span class="truncate max-w-36">{{ $trx->party_name }}</span></span>
                                @else
                                    <span class="text-outline">—</span>
                                @endif
                            </td>
                            <td class="max-w-56"><p class="truncate">{{ $trx->description }}</p>
                                <p class="text-body-sm text-on-surface-variant flex items-center gap-1">{{ $trx->creator->name }} @if ($trx->attachments_count)<span class="material-symbols-outlined text-[14px]! text-primary">attach_file</span>{{ $trx->attachments_count }}@endif</p>
                            </td>
                            <td class="text-right font-semibold whitespace-nowrap">{{ Money::format($trx->amount) }}</td>
                            <td class="text-center"><x-status-badge :status="$trx->status" /></td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('transactions.show', $trx) }}" class="btn-ghost btn-sm" title="Lihat"><span class="material-symbols-outlined text-[18px]!">visibility</span></a>
                                @can('update', $trx)
                                    <a href="{{ route('transactions.edit', $trx) }}" class="btn-ghost btn-sm" title="Edit"><span class="material-symbols-outlined text-[18px]!">edit</span></a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty-state icon="receipt_long" title="Tidak ada transaksi" message="Belum ada transaksi yang cocok dengan filter." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-space-md">{{ $transactions->links() }}</div>
    </section>
</x-app-layout>
