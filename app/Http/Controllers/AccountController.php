<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\NormalBalance;
use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $all = Account::withCount('journalLines')->orderBy('code')->get();
        $type = AccountType::tryFrom((string) $request->query('type'));
        $search = trim((string) $request->query('q'));
        $postableOnly = $request->boolean('postable');

        $filtered = $all
            ->when($type, fn (Collection $c) => $c->where('type', $type))
            ->when($postableOnly, fn (Collection $c) => $c->where('is_postable', true))
            ->when($search !== '', fn (Collection $c) => $c->filter(
                fn (Account $a) => str_contains(mb_strtolower($a->code.' '.$a->name), mb_strtolower($search))
            ));

        return view('master.accounts.index', [
            'accounts' => $this->treeOrder($filtered),
            'all' => $all,
            'type' => $type,
            'search' => $search,
            'postableOnly' => $postableOnly,
            'typeCounts' => $all->countBy(fn (Account $a) => $a->type->value),
        ]);
    }

    public function create(Request $request): View
    {
        $account = new Account([
            'is_postable' => true,
            'is_active' => true,
            'parent_id' => $request->integer('parent') ?: null,
        ]);
        if ($account->parent_id && $parent = Account::find($account->parent_id)) {
            $account->type = $parent->type;
            $account->normal_balance = $parent->normal_balance;
        }

        return view('master.accounts.form', ['account' => $account, 'parents' => $this->parentOptions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $account = Account::create($data);

        return redirect()->route('master.accounts.index')->with('success', "Akun {$account->code} - {$account->name} berhasil ditambahkan.");
    }

    public function edit(Account $account): View
    {
        return view('master.accounts.form', [
            'account' => $account,
            'parents' => $this->parentOptions($account),
            'isUsed' => $account->isUsed(),
        ]);
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $data = $this->validated($request, $account);

        // Akun yang sudah dipakai: kode, tipe, dan saldo normal dikunci agar riwayat tetap konsisten.
        if ($account->journalLines()->exists()) {
            foreach (['code', 'type', 'normal_balance'] as $locked) {
                $current = $account->{$locked} instanceof \BackedEnum ? $account->{$locked}->value : $account->{$locked};
                if ((string) $data[$locked] !== (string) $current) {
                    throw new BusinessRuleException('Kode, tipe, dan saldo normal akun yang sudah memiliki jurnal tidak dapat diubah.');
                }
            }
            if (! $data['is_postable']) {
                throw new BusinessRuleException('Akun yang sudah memiliki jurnal tidak dapat diubah menjadi akun induk (non-postable).');
            }
        }

        $account->update($data);

        return redirect()->route('master.accounts.index')->with('success', "Akun {$account->code} berhasil diperbarui.");
    }

    /** Hapus hanya bila akun belum pernah dipakai; selain itu harus dinonaktifkan. */
    public function destroy(Account $account): RedirectResponse
    {
        if ($account->isUsed()) {
            throw new BusinessRuleException("Akun {$account->code} sudah dipakai (jurnal/transaksi/template/sub-akun) sehingga tidak dapat dihapus. Nonaktifkan saja.");
        }

        $account->delete();

        return redirect()->route('master.accounts.index')->with('success', "Akun {$account->code} dihapus.");
    }

    public function toggle(Account $account): RedirectResponse
    {
        $account->update(['is_active' => ! $account->is_active]);

        return back()->with('success', "Akun {$account->code} ".($account->is_active ? 'diaktifkan' : 'dinonaktifkan').'.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Account $account = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[0-9][0-9\-\.]*$/', Rule::unique('accounts', 'code')->ignore($account?->id)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'normal_balance' => ['required', Rule::enum(NormalBalance::class)],
            'parent_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('is_postable', false)],
            'is_postable' => ['boolean'],
            'is_active' => ['boolean'],
            'is_cash' => ['boolean'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'code.regex' => 'Kode akun hanya boleh berisi angka, titik, dan tanda hubung (mis. 1-1001).',
            'parent_id.exists' => 'Parent harus akun induk (non-postable).',
        ]);

        $data['is_postable'] = $request->boolean('is_postable');
        $data['is_active'] = $request->boolean('is_active');
        $data['is_cash'] = $request->boolean('is_cash') && $data['is_postable'];

        if ($account && isset($data['parent_id'])) {
            // Cegah siklus hirarki.
            $parent = Account::find($data['parent_id']);
            while ($parent) {
                if ($parent->id === $account->id) {
                    throw new BusinessRuleException('Parent akun tidak boleh akun itu sendiri atau turunannya.');
                }
                $parent = $parent->parent;
            }
        }

        if (! $data['is_postable'] && $account && $account->journalLines()->exists()) {
            throw new BusinessRuleException('Akun yang sudah memiliki jurnal harus tetap postable.');
        }

        return $data;
    }

    private function parentOptions(?Account $except = null): Collection
    {
        return Account::where('is_postable', false)
            ->when($except, fn ($q) => $q->where('id', '!=', $except->id))
            ->orderBy('code')
            ->get();
    }

    /**
     * Urutkan akun sebagai pohon (induk diikuti anak-anaknya), dengan atribut depth.
     *
     * @param  Collection<int, Account>  $accounts
     * @return Collection<int, Account>
     */
    private function treeOrder(Collection $accounts): Collection
    {
        $byParent = $accounts->groupBy(fn (Account $a) => $a->parent_id ?? 0);
        $ids = $accounts->pluck('id')->flip();
        $result = collect();

        $walk = function ($parentId, int $depth) use (&$walk, $byParent, $result) {
            foreach ($byParent->get($parentId, collect())->sortBy('code') as $account) {
                $account->setAttribute('depth', $depth);
                $result->push($account);
                $walk($account->id, $depth + 1);
            }
        };

        // Akar = akun tanpa parent, atau yang parent-nya tidak lolos filter.
        $roots = $accounts->filter(fn (Account $a) => ! $a->parent_id || ! $ids->has($a->parent_id))->sortBy('code');
        foreach ($roots as $root) {
            $depth = $root->parent_id ? 1 : 0;
            $root->setAttribute('depth', $depth);
            $result->push($root);
            $walk($root->id, $depth + 1);
        }

        return $result;
    }
}
