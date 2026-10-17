<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\JournalTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JournalTemplateController extends Controller
{
    public function index(): View
    {
        return view('master.templates.index', [
            'templates' => JournalTemplate::with(['debitAccount', 'creditAccount'])->get()->keyBy(fn ($t) => $t->transaction_type->value),
            'accounts' => Account::postable()->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $postable = Rule::exists('accounts', 'id')->where('is_postable', true)->where('is_active', true);
        $rules = [];
        foreach (TransactionType::cases() as $type) {
            $rules["templates.{$type->value}.debit_account_id"] = ['required', 'integer', $postable];
            $rules["templates.{$type->value}.credit_account_id"] = ['required', 'integer', $postable, "different:templates.{$type->value}.debit_account_id"];
        }

        $data = $request->validate($rules, [
            '*.required' => 'Akun wajib dipilih.',
            '*.different' => 'Akun debit dan kredit tidak boleh sama.',
        ]);

        foreach (TransactionType::cases() as $type) {
            JournalTemplate::updateOrCreate(
                ['transaction_type' => $type],
                $data['templates'][$type->value],
            );
        }

        return back()->with('success', 'Template jurnal otomatis berhasil disimpan.');
    }
}
