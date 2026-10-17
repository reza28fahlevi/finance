@php use App\Enums\AccountType; @endphp
<x-app-layout title="Chart of Accounts">
    <x-page-header eyebrow="Master Data Akuntansi" context="Buku Besar Terpusat" title="Chart of Accounts" accent="(Bagan Akun)"
                   subtitle="Kelola struktur hirarki akun, saldo normal, dan status posting.">
        <x-slot:actions>
            @can('master.manage')
                <a href="{{ route('master.accounts.create') }}" class="btn-primary"><span class="material-symbols-outlined">add_circle</span> Tambah Akun Baru</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @php
        $postable = $all->where('is_postable', true)->count();
    @endphp
    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-space-md mb-space-lg">
        <x-stat-card label="Total Akun Terdaftar" :value="$all->count().' Akun'" icon="account_tree" :hint="$typeCounts->count().' klasifikasi'" />
        <x-stat-card label="Akun Header (Induk)" :value="($all->count() - $postable).' Header'" icon="folder" hint="Hanya agregasi • Non-postable" tone="tertiary" />
        <x-stat-card label="Akun Postable" :value="$postable.' Akun'" icon="edit_note" :hint="($all->count() ? round($postable / $all->count() * 100, 1) : 0).'% dapat dijurnal'" tone="secondary" />
        <x-stat-card label="Akun Nonaktif" :value="$all->where('is_active', false)->count().' Akun'" icon="block" hint="Tidak dapat dipakai transaksi baru" tone="tertiary" />
    </section>

    <section class="glass-card p-space-md mb-space-lg flex flex-col gap-space-md">
        <div class="flex items-center gap-space-sm flex-wrap">
            <a href="{{ route('master.accounts.index', request()->except('type')) }}" @class(['chip', 'chip-active' => ! $type])>Semua ({{ $all->count() }})</a>
            @foreach (AccountType::cases() as $t)
                <a href="{{ route('master.accounts.index', array_merge(request()->query(), ['type' => $t->value])) }}" @class(['chip', 'chip-active' => $type === $t])>
                    {{ $t->label() }} <span class="opacity-60">{{ $t->codePrefix() }}-xxxx · {{ $typeCounts[$t->value] ?? 0 }}</span>
                </a>
            @endforeach
        </div>
        <form method="GET" class="flex items-center gap-space-md flex-wrap">
            @if ($type)<input type="hidden" name="type" value="{{ $type->value }}">@endif
            <div class="relative flex-1 min-w-60">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]!">search</span>
                <input type="search" name="q" value="{{ $search }}" class="form-input pl-9" placeholder="Cari nama atau kode akun...">
            </div>
            <label class="inline-flex items-center gap-2 text-label-md text-on-surface-variant cursor-pointer">
                <input type="checkbox" name="postable" value="1" class="form-check" @checked($postableOnly) onchange="this.form.submit()"> Hanya akun postable
            </label>
            <button class="btn-secondary btn-sm">Terapkan</button>
            <span class="text-label-sm text-on-surface-variant ml-auto">Menampilkan <b>{{ $accounts->count() }}</b> akun</span>
        </form>
    </section>

    <section class="glass-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kode Akun</th><th>Nama Akun & Hirarki</th><th>Tipe Akun</th><th class="text-center">Saldo Normal</th>
                        <th class="text-center">Postable</th><th class="text-center">Status</th>
                        @can('master.manage')<th class="text-right">Aksi</th>@endcan
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr @class(['opacity-50' => ! $account->is_active])>
                            <td class="font-mono whitespace-nowrap {{ $account->is_postable ? 'text-on-surface-variant' : 'text-primary font-bold text-label-lg' }}"
                                style="padding-left: {{ 1 + $account->depth * 1.25 }}rem">
                                @if ($account->depth > 0)<span class="text-outline">•</span>@endif {{ $account->code }}
                            </td>
                            <td>
                                <div class="flex items-center gap-2" style="padding-left: {{ $account->depth * 1 }}rem">
                                    @if (! $account->is_postable)
                                        <span class="material-symbols-outlined text-[18px]! text-on-surface-variant">{{ $account->depth === 0 ? 'folder' : 'folder_open' }}</span>
                                        <span class="{{ $account->depth === 0 ? 'uppercase text-label-lg' : 'text-label-md' }}">{{ $account->name }}</span>
                                        @if ($account->depth === 0)<span class="px-1.5 py-0.5 rounded bg-surface-container-highest text-label-sm text-on-surface-variant">Induk Utama</span>@endif
                                    @else
                                        <span>{{ $account->name }}</span>
                                        @if ($account->is_cash)<span class="px-1.5 py-0.5 rounded bg-primary-container/15 text-primary text-label-sm">Kas/Bank</span>@endif
                                    @endif
                                </div>
                            </td>
                            <td><span class="badge bg-secondary-container/60 text-on-secondary-container">{{ $account->type->label() }}</span></td>
                            <td class="text-center text-label-md {{ $account->normal_balance->value === 'CREDIT' ? 'text-primary' : '' }}">{{ $account->normal_balance->label() }}</td>
                            <td class="text-center">
                                @if ($account->is_postable)
                                    <span class="badge badge-dot bg-primary-container/15 text-primary">Postable</span>
                                @else
                                    <span class="badge bg-surface-container-highest text-outline">Induk</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge badge-dot {{ $account->is_active ? 'bg-surface-container-highest text-on-surface' : 'bg-error-container/40 text-on-error-container' }}">{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </td>
                            @can('master.manage')
                                <td class="text-right whitespace-nowrap">
                                    @unless ($account->is_postable)
                                        <a href="{{ route('master.accounts.create', ['parent' => $account->id]) }}" class="btn-ghost btn-sm" title="Tambah sub-akun"><span class="material-symbols-outlined text-[18px]!">add_circle</span></a>
                                    @endunless
                                    <a href="{{ route('master.accounts.edit', $account) }}" class="btn-ghost btn-sm" title="Edit"><span class="material-symbols-outlined text-[18px]!">edit</span></a>
                                    <form method="POST" action="{{ route('master.accounts.toggle', $account) }}" class="inline">
                                        @csrf @method('PATCH')
                                        <button class="btn-ghost btn-sm" title="{{ $account->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"><span class="material-symbols-outlined text-[18px]!">{{ $account->is_active ? 'toggle_on' : 'toggle_off' }}</span></button>
                                    </form>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="account_tree" title="Tidak ada akun" message="Ubah filter atau tambahkan akun baru." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-space-lg glass-card p-space-md flex items-start gap-space-sm">
        <span class="w-10 h-10 rounded-xl bg-secondary-container/50 text-primary flex items-center justify-center shrink-0"><span class="material-symbols-outlined">shield</span></span>
        <div>
            <p class="text-label-lg">Prinsip Integritas Akuntansi & Aturan Perubahan COA</p>
            <p class="text-body-sm text-on-surface-variant">Akun yang telah memiliki riwayat jurnal/transaksi <b class="text-primary">tidak dapat dihapus</b> demi menjaga konsistensi rekam jejak keuangan — hanya dapat dinonaktifkan. Kode, tipe, dan saldo normal akun yang sudah dijurnal dikunci.</p>
        </div>
    </section>
</x-app-layout>
