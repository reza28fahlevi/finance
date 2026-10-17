@php use App\Enums\Role; @endphp
<x-app-layout title="Pengguna">
    <x-page-header eyebrow="Pengaturan" title="Pengguna" subtitle="Kelola pengguna, peran (role), status aktif, dan reset password. Pengguna nonaktif tidak dapat login.">
        <x-slot:actions>
            <a href="{{ route('settings.users.create') }}" class="btn-primary"><span class="material-symbols-outlined">person_add</span> Tambah Pengguna</a>
        </x-slot:actions>
    </x-page-header>

    <section class="glass-card p-space-md mb-space-lg flex flex-col gap-space-md">
        <div class="flex items-center gap-space-sm flex-wrap">
            <a href="{{ route('settings.users.index') }}" @class(['chip', 'chip-active' => ! request('role')])>Semua ({{ $roleCounts->sum() }})</a>
            @foreach (Role::cases() as $r)
                <a href="{{ route('settings.users.index', ['role' => $r->value]) }}" @class(['chip', 'chip-active' => request('role') === $r->value])>{{ $r->label() }} ({{ $roleCounts[$r->value] ?? 0 }})</a>
            @endforeach
        </div>
        <form method="GET" class="flex gap-space-sm">
            @if (request('role'))<input type="hidden" name="role" value="{{ request('role') }}">@endif
            <input type="search" name="q" value="{{ request('q') }}" class="form-input" placeholder="Cari nama atau email...">
            <button class="btn-secondary">Cari</button>
        </form>
    </section>

    <section class="glass-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th class="text-center">Status</th><th>Dibuat</th><th></th></tr></thead>
                <tbody>
                    @foreach ($users as $u)
                        <tr @class(['opacity-60' => ! $u->is_active])>
                            <td><span class="flex items-center gap-2"><span class="w-8 h-8 rounded-full bg-secondary-container text-on-secondary-container text-label-sm flex items-center justify-center">{{ $u->initials() }}</span>{{ $u->name }}</span></td>
                            <td>{{ $u->email }}</td>
                            <td><span class="badge bg-surface-container-highest text-on-surface normal-case">{{ $u->role->label() }}</span></td>
                            <td class="text-center"><span class="badge badge-dot {{ $u->is_active ? 'bg-success-container/50 text-success' : 'bg-error-container/50 text-on-error-container' }}">{{ $u->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="text-body-sm text-on-surface-variant">{{ $u->created_at->translatedFormat('d M Y') }}</td>
                            <td class="text-right"><a href="{{ route('settings.users.edit', $u) }}" class="btn-ghost btn-sm"><span class="material-symbols-outlined text-[18px]!">edit</span></a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-space-md">{{ $users->links() }}</div>
    </section>
</x-app-layout>
