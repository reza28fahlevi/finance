<x-app-layout title="Profil Saya">
    <x-page-header eyebrow="Akun" title="Profil Saya" :subtitle="'Peran: '.$user->role->label().'. Peran dan status akun hanya dapat diubah oleh Admin.'" />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-space-lg">
        <section class="glass-card p-space-lg">
            <x-card-title icon="badge" title="Informasi Profil" subtitle="Perbarui nama dan alamat email akun Anda." />
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-space-md">
                @csrf
                @method('patch')
                <x-field label="Nama" name="name" required>
                    <input id="name" name="name" type="text" class="form-input" value="{{ old('name', $user->name) }}" required autocomplete="name">
                </x-field>
                <x-field label="Email" name="email" required>
                    <input id="email" name="email" type="email" class="form-input" value="{{ old('email', $user->email) }}" required autocomplete="username">
                </x-field>
                <button type="submit" class="btn-primary">Simpan Profil</button>
            </form>
        </section>

        <section class="glass-card p-space-lg">
            <x-card-title icon="lock" title="Ubah Password" subtitle="Gunakan password yang panjang dan acak." />
            <form method="POST" action="{{ route('password.update') }}" class="space-y-space-md">
                @csrf
                @method('put')
                <div>
                    <label for="current_password" class="form-label">Password Saat Ini</label>
                    <input id="current_password" name="current_password" type="password" class="form-input" autocomplete="current-password">
                    <x-input-error :messages="$errors->updatePassword->get('current_password')" />
                </div>
                <div>
                    <label for="password" class="form-label">Password Baru</label>
                    <input id="password" name="password" type="password" class="form-input" autocomplete="new-password">
                    <x-input-error :messages="$errors->updatePassword->get('password')" />
                </div>
                <div>
                    <label for="password_confirmation" class="form-label">Konfirmasi Password Baru</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="form-input" autocomplete="new-password">
                </div>
                <button type="submit" class="btn-primary">Ubah Password</button>
            </form>
        </section>
    </div>
</x-app-layout>
