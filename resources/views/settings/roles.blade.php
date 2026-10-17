@php use App\Enums\Role; use App\Support\Permissions; @endphp
<x-app-layout title="Role & Permission">
    <x-page-header eyebrow="Pengaturan" context="RBAC" title="Role & Permission"
                   subtitle="Atur hak akses menu untuk setiap peran. Perubahan berlaku langsung untuk semua pengguna dengan peran tersebut dan tercatat di audit log.">
        <x-slot:actions>
            <form method="POST" action="{{ route('settings.roles.reset') }}" onsubmit="return confirm('Kembalikan seluruh hak akses ke default? Perubahan yang sudah dibuat akan hilang.')">
                @csrf
                <button class="btn-secondary"><span class="material-symbols-outlined">restart_alt</span> Reset ke Default</button>
            </form>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('settings.roles.update') }}"
          x-data="{
              matrix: @js($matrix),
              defaults: @js($defaults),
              implies: @js(Permissions::IMPLIES),
              locked: @js(Permissions::LOCKED),
              toggle(role, perm, checked) {
                  let list = this.matrix[role] ?? [];
                  if (checked) {
                      // Centang permission turunan yang dibutuhkan (mis. Kelola → Lihat)
                      (this.implies[perm] ?? []).forEach(p => { if (!list.includes(p)) list.push(p); });
                  } else {
                      // Lepas permission yang bergantung pada permission ini
                      Object.entries(this.implies).forEach(([parent, deps]) => {
                          if (deps.includes(perm) && !(this.locked[role] ?? []).includes(parent)) list = list.filter(p => p !== parent);
                      });
                  }
                  this.matrix[role] = list;
              },
              has(role, perm) { return (this.matrix[role] ?? []).includes(perm); },
              changed(role, perm) { return this.has(role, perm) !== (this.defaults[role] ?? []).includes(perm); },
              get changeCount() {
                  let n = 0;
                  Object.keys(this.defaults).forEach(r => @js(Permissions::keys()).forEach(p => { if (this.changed(r, p)) n++; }));
                  return n;
              },
          }">
        @csrf @method('PUT')

        <section class="glass-card p-space-md mb-space-lg flex items-start gap-space-sm">
            <span class="w-10 h-10 rounded-xl bg-secondary-container/50 text-primary flex items-center justify-center shrink-0"><span class="material-symbols-outlined">info</span></span>
            <ul class="text-body-sm text-on-surface-variant space-y-1">
                <li>Permission <b class="text-on-surface">kelola</b> otomatis menyertakan permission <b class="text-on-surface">lihat</b> yang dibutuhkan (mis. <i>Input Transaksi</i> → <i>Lihat Transaksi</i>).</li>
                <li>Aturan bisnis tetap berlaku apa pun hak aksesnya: <b class="text-on-surface">Maker ≠ Checker</b>, periode CLOSED terkunci, jurnal POSTED immutable.</li>
                <li><span class="material-symbols-outlined text-[14px]! align-middle">lock</span> = wajib untuk Admin agar sistem tidak terkunci. <span class="inline-block w-2 h-2 rounded-full bg-primary-container align-middle"></span> = berbeda dari default.</li>
            </ul>
        </section>

        <section class="glass-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="data-table min-w-[56rem]">
                    <thead>
                        <tr>
                            <th class="w-80">Menu / Hak Akses</th>
                            @foreach (Role::cases() as $role)
                                <th class="text-center">
                                    <span class="block">{{ $role->label() }}</span>
                                    <span class="block normal-case font-normal text-on-surface-variant/70">{{ $userCounts[$role->value] ?? 0 }} pengguna</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="bg-surface-container-lowest/30">
                            <td><p class="text-label-lg">Dashboard</p><p class="text-body-sm text-on-surface-variant">Selalu tersedia untuk semua peran</p></td>
                            @foreach (Role::cases() as $role)
                                <td class="text-center"><span class="material-symbols-outlined text-primary">check</span></td>
                            @endforeach
                        </tr>
                        @foreach (Permissions::GROUPS as $group => $items)
                            <tr><td colspan="{{ count(Role::cases()) + 1 }}" class="pt-space-lg! pb-1! text-label-sm uppercase tracking-wider text-primary border-t-0!">{{ $group }}</td></tr>
                            @foreach ($items as $perm => [$label, $hint])
                                <tr>
                                    <td>
                                        <p class="text-label-lg">{{ $label }}</p>
                                        <p class="text-body-sm text-on-surface-variant">{{ $hint }}</p>
                                        <p class="text-[11px] font-mono text-outline">{{ $perm }}</p>
                                    </td>
                                    @foreach (Role::cases() as $role)
                                        @php $locked = Permissions::isLocked($role, $perm); @endphp
                                        <td class="text-center">
                                            <label class="relative inline-flex items-center justify-center w-9 h-9 rounded-lg {{ $locked ? '' : 'cursor-pointer hover:bg-white/5' }}"
                                                   title="{{ $role->label() }} — {{ $label }}">
                                                @if ($locked)
                                                    <input type="hidden" name="permissions[{{ $role->value }}][]" value="{{ $perm }}">
                                                    <input type="checkbox" class="form-check opacity-60" checked disabled aria-label="{{ $role->label() }} {{ $label }} (wajib)">
                                                    <span class="material-symbols-outlined text-[13px]! absolute -top-0.5 -right-0.5 text-outline">lock</span>
                                                @else
                                                    <input type="checkbox" class="form-check w-5 h-5" name="permissions[{{ $role->value }}][]" value="{{ $perm }}"
                                                           :checked="has('{{ $role->value }}', '{{ $perm }}')"
                                                           @change="toggle('{{ $role->value }}', '{{ $perm }}', $event.target.checked)"
                                                           aria-label="{{ $role->label() }} {{ $label }}">
                                                    <span x-show="changed('{{ $role->value }}', '{{ $perm }}')" x-cloak class="absolute top-0.5 right-0.5 w-2 h-2 rounded-full bg-primary-container"></span>
                                                @endif
                                            </label>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="mt-space-lg flex items-center gap-space-md flex-wrap">
            <button class="btn-primary"><span class="material-symbols-outlined">save</span> Simpan Hak Akses</button>
            <span class="text-body-sm text-on-surface-variant" x-text="changeCount ? changeCount + ' sel berbeda dari default' : 'Sesuai default'"></span>
        </div>
    </form>
</x-app-layout>
