@php use App\Enums\Role; $editing = $user->exists; @endphp
<x-app-layout :title="$editing ? 'Edit Pengguna' : 'Tambah Pengguna'">
    <x-page-header eyebrow="Pengaturan · Pengguna" :title="$editing ? 'Edit '.$user->name : 'Tambah Pengguna'">
        <x-slot:actions><a href="{{ route('settings.users.index') }}" class="btn-secondary"><span class="material-symbols-outlined">arrow_back</span> Kembali</a></x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-space-lg items-start">
        <form method="POST" action="{{ $editing ? route('settings.users.update', $user) : route('settings.users.store') }}" class="glass-card p-space-lg space-y-space-md">
            @csrf
            @if ($editing) @method('PUT') @endif
            <x-card-title icon="badge" title="Data Pengguna" />
            <x-field label="Nama Lengkap" name="name" required><input id="name" name="name" class="form-input" value="{{ old('name', $user->name) }}" required maxlength="255"></x-field>
            <x-field label="Email" name="email" required><input id="email" type="email" name="email" class="form-input" value="{{ old('email', $user->email) }}" required></x-field>
            <x-field label="Peran (Role)" name="role" required>
                <select id="role" name="role" class="form-input" required>
                    @foreach (Role::cases() as $r)
                        <option value="{{ $r->value }}" @selected(old('role', $user->role?->value) === $r->value)>{{ $r->label() }}</option>
                    @endforeach
                </select>
            </x-field>
            @unless ($editing)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
                    <x-field label="Password" name="password" required><input id="password" type="password" name="password" class="form-input" required autocomplete="new-password"></x-field>
                    <x-field label="Konfirmasi Password" name="password_confirmation" required><input id="password_confirmation" type="password" name="password_confirmation" class="form-input" required autocomplete="new-password"></x-field>
                </div>
            @endunless
            <label class="glass-panel p-space-md flex items-start gap-2 cursor-pointer">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="form-check mt-0.5" @checked(old('is_active', $user->is_active))>
                <span><span class="text-label-lg block">Aktif</span><span class="text-body-sm text-on-surface-variant">Pengguna nonaktif tidak dapat login; riwayat & audit tetap tersimpan.</span></span>
            </label>
            <button class="btn-primary"><span class="material-symbols-outlined">save</span> Simpan</button>
        </form>

        @if ($editing)
            <form method="POST" action="{{ route('settings.users.password', $user) }}" class="glass-card p-space-lg space-y-space-md">
                @csrf @method('PUT')
                <x-card-title icon="lock_reset" title="Reset Password" subtitle="Tetapkan password baru untuk pengguna ini. Aksi tercatat di audit log." />
                <x-field label="Password Baru" name="password" required><input id="reset_password" type="password" name="password" class="form-input" required autocomplete="new-password"></x-field>
                <x-field label="Konfirmasi Password Baru" name="password_confirmation" required><input id="reset_password_confirmation" type="password" name="password_confirmation" class="form-input" required autocomplete="new-password"></x-field>
                <button class="btn-secondary"><span class="material-symbols-outlined">lock_reset</span> Reset Password</button>
            </form>
        @endif
    </div>
</x-app-layout>
