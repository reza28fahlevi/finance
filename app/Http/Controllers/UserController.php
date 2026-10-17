<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->query('role')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$request->query('q').'%')
                ->orWhere('email', 'like', '%'.$request->query('q').'%')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('settings.users.index', [
            'users' => $users,
            'roleCounts' => User::selectRaw('role, COUNT(*) as c')->groupBy('role')->pluck('c', 'role'),
        ]);
    }

    public function create(): View
    {
        return view('settings.users.form', ['user' => new User(['is_active' => true, 'role' => Role::Staff])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'lowercase', 'unique:users,email'],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create($data + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('settings.users.index')->with('success', "Pengguna {$user->name} berhasil dibuat.");
    }

    public function edit(User $user): View
    {
        return view('settings.users.form', ['user' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'lowercase', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::enum(Role::class)],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        if ($user->is($request->user()) && (! $data['is_active'] || $data['role'] !== Role::Admin->value)) {
            throw new BusinessRuleException('Anda tidak dapat menonaktifkan atau mengubah peran akun Anda sendiri.');
        }

        $user->update($data);

        return redirect()->route('settings.users.index')->with('success', "Pengguna {$user->name} diperbarui.");
    }

    public function resetPassword(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update(['password' => $data['password']]);
        $audit->log('user.password_reset', $user, null, null, "Password direset oleh {$request->user()->name}");

        return back()->with('success', "Password {$user->name} berhasil direset.");
    }
}
