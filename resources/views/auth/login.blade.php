<x-guest-layout>
    <div class="mb-space-lg">
        <h2 class="text-headline-md text-on-surface">Selamat datang kembali</h2>
        <p class="text-body-md text-on-surface-variant mt-1">Masuk untuk mengelola transaksi dan buku besar.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-space-md">
        @csrf

        <div>
            <label for="email" class="form-label">Email</label>
            <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]!">mail</span>
                <input id="email" class="form-input pl-10" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="nama@perusahaan.com">
            </div>
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div x-data="{ show: false }">
            <label for="password" class="form-label">Password</label>
            <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]!">lock</span>
                <input id="password" class="form-input pl-10 pr-10" :type="show ? 'text' : 'password'" type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface" @click="show = !show" :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'">
                    <span class="material-symbols-outlined text-[18px]!" x-text="show ? 'visibility_off' : 'visibility'">visibility</span>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <label for="remember_me" class="inline-flex items-center gap-2 cursor-pointer">
            <input id="remember_me" type="checkbox" class="form-check" name="remember">
            <span class="text-body-sm text-on-surface-variant">Ingat saya</span>
        </label>

        <button type="submit" class="btn-primary w-full py-3">
            <span class="material-symbols-outlined">login</span> Masuk
        </button>

        <p class="text-body-sm text-on-surface-variant/70 text-center">Lupa password? Hubungi Admin untuk reset.</p>
    </form>
</x-guest-layout>
