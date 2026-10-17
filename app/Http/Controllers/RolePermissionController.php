<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RolePermissionController extends Controller
{
    public function __construct(private Permissions $permissions) {}

    public function edit(): View
    {
        return view('settings.roles', [
            'matrix' => $this->permissions->map(),
            'defaults' => Permissions::defaults(),
            'userCounts' => User::selectRaw('role, COUNT(*) as c')->groupBy('role')->pluck('c', 'role'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['string', Rule::in(Permissions::keys())],
        ]);

        $roles = array_map(fn (Role $r) => $r->value, Role::cases());
        $matrix = array_intersect_key($data['permissions'] ?? [], array_flip($roles));
        $changes = $this->permissions->sync($matrix);

        if ($changes === []) {
            return back()->with('success', 'Tidak ada perubahan hak akses.');
        }

        $summary = collect($changes)->map(function ($c, $role) {
            $parts = [];
            if ($c['added']) {
                $parts[] = '+'.count($c['added']);
            }
            if ($c['removed']) {
                $parts[] = '−'.count($c['removed']);
            }

            return Role::from($role)->label().' ('.implode(' ', $parts).')';
        })->implode(', ');

        return back()->with('success', "Hak akses diperbarui: {$summary}. Berlaku langsung untuk semua pengguna.");
    }

    public function reset(): RedirectResponse
    {
        $this->permissions->resetToDefaults();

        return back()->with('success', 'Hak akses dikembalikan ke default (matriks RANCANGAN.md 10.4).');
    }
}
