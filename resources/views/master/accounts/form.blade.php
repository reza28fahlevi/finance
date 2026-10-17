@php use App\Enums\AccountType; use App\Enums\NormalBalance; @endphp
@php $editing = $account->exists; $isUsed = $isUsed ?? false; @endphp
<x-app-layout :title="$editing ? 'Edit Akun' : 'Tambah Akun'">
    <x-page-header eyebrow="Chart of Accounts" :title="$editing ? 'Edit Akun '.$account->code : 'Tambah Akun Baru'"
                   subtitle="Akun induk (non-postable) hanya untuk agregasi; jurnal hanya dapat memakai akun detail (postable).">
        <x-slot:actions>
            <a href="{{ route('master.accounts.index') }}" class="btn-secondary"><span class="material-symbols-outlined">arrow_back</span> Kembali</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $editing ? route('master.accounts.update', $account) : route('master.accounts.store') }}"
          class="glass-card p-space-lg max-w-3xl"
          x-data="{ type: '{{ old('type', $account->type?->value) }}', normal: '{{ old('normal_balance', $account->normal_balance?->value) }}', postable: {{ old('is_postable', $account->is_postable) ? 'true' : 'false' }},
                    defaults: { ASSET: 'DEBIT', EXPENSE: 'DEBIT', LIABILITY: 'CREDIT', EQUITY: 'CREDIT', REVENUE: 'CREDIT' } }">
        @csrf
        @if ($editing) @method('PUT') @endif

        @if ($isUsed)
            <div class="mb-space-md p-space-sm rounded-xl bg-secondary-container/30 text-body-sm text-on-surface-variant flex gap-2">
                <span class="material-symbols-outlined text-primary">info</span>
                Akun ini sudah dipakai. Akun tidak dapat dihapus; kode/tipe/saldo normal dikunci bila sudah memiliki jurnal.
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
            <x-field label="Kode Akun" name="code" required hint="Contoh: 1-1001">
                <input id="code" name="code" class="form-input font-mono" value="{{ old('code', $account->code) }}" required maxlength="20">
            </x-field>
            <x-field label="Nama Akun" name="name" required>
                <input id="name" name="name" class="form-input" value="{{ old('name', $account->name) }}" required maxlength="255">
            </x-field>
            <x-field label="Tipe Akun" name="type" required>
                <select id="type" name="type" class="form-input" x-model="type" @change="normal = defaults[type] ?? normal" required>
                    <option value="">— Pilih tipe —</option>
                    @foreach (AccountType::cases() as $t)
                        <option value="{{ $t->value }}">{{ $t->label() }} ({{ $t->codePrefix() }}-xxxx)</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Saldo Normal" name="normal_balance" required>
                <select id="normal_balance" name="normal_balance" class="form-input" x-model="normal" required>
                    @foreach (NormalBalance::cases() as $n)
                        <option value="{{ $n->value }}">{{ $n->label() }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Parent (Akun Induk)" name="parent_id" class="sm:col-span-2">
                <select id="parent_id" name="parent_id" class="form-input">
                    <option value="">— Tanpa parent (akun level atas) —</option>
                    @foreach ($parents as $p)
                        <option value="{{ $p->id }}" @selected((int) old('parent_id', $account->parent_id) === $p->id)>{{ $p->code }} — {{ $p->name }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Keterangan" name="description" class="sm:col-span-2">
                <input id="description" name="description" class="form-input" value="{{ old('description', $account->description) }}" maxlength="255" placeholder="Opsional, mis. nomor rekening bank">
            </x-field>
        </div>

        <div class="mt-space-lg grid grid-cols-1 sm:grid-cols-3 gap-space-sm">
            <label class="glass-panel p-space-md flex items-start gap-2 cursor-pointer">
                <input type="hidden" name="is_postable" value="0">
                <input type="checkbox" name="is_postable" value="1" class="form-check mt-0.5" x-model="postable">
                <span><span class="text-label-lg block">Postable</span><span class="text-body-sm text-on-surface-variant">Akun detail yang dapat dijurnal</span></span>
            </label>
            <label class="glass-panel p-space-md flex items-start gap-2 cursor-pointer">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="form-check mt-0.5" @checked(old('is_active', $account->is_active))>
                <span><span class="text-label-lg block">Aktif</span><span class="text-body-sm text-on-surface-variant">Dapat dipakai transaksi baru</span></span>
            </label>
            <label class="glass-panel p-space-md flex items-start gap-2 cursor-pointer" :class="postable ? '' : 'opacity-40 pointer-events-none'">
                <input type="hidden" name="is_cash" value="0">
                <input type="checkbox" name="is_cash" value="1" class="form-check mt-0.5" @checked(old('is_cash', $account->is_cash))>
                <span><span class="text-label-lg block">Akun Kas/Bank</span><span class="text-body-sm text-on-surface-variant">Tampil di ringkasan kas dashboard</span></span>
            </label>
        </div>

        <div class="mt-space-lg flex items-center justify-between gap-space-sm flex-wrap">
            <button type="submit" class="btn-primary"><span class="material-symbols-outlined">save</span> {{ $editing ? 'Simpan Perubahan' : 'Simpan Akun' }}</button>
        </div>
    </form>

    @if ($editing && ! $isUsed)
        <form method="POST" action="{{ route('master.accounts.destroy', $account) }}" class="mt-space-md max-w-3xl" onsubmit="return confirm('Hapus akun {{ $account->code }}? Tindakan ini tidak dapat dibatalkan.')">
            @csrf @method('DELETE')
            <button class="btn-danger btn-sm"><span class="material-symbols-outlined text-[16px]!">delete</span> Hapus Akun (belum pernah dipakai)</button>
        </form>
    @endif
</x-app-layout>
