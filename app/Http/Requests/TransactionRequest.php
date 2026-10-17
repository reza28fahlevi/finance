<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use App\Support\Settings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    /** Otorisasi dicek sebelum validasi agar pengguna tanpa hak akses langsung mendapat 403. */
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');

        return $transaction instanceof Transaction
            ? $this->user()->can('update', $transaction)
            : $this->user()->can('create', Transaction::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $postable = Rule::exists('accounts', 'id')->where('is_postable', true)->where('is_active', true);
        $mimes = implode(',', config('finance.attachment.mimes'));
        $maxKb = app(Settings::class)->get('attachment_max_kb');

        return [
            'trx_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:trx_date'],
            'description' => ['required', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'debit_account_id' => ['required', 'integer', $postable],
            'credit_account_id' => ['required', 'integer', $postable, 'different:debit_account_id'],
            'party_name' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', "mimes:{$mimes}", 'mimetypes:application/pdf,image/jpeg,image/png', "max:{$maxKb}"],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.gt' => 'Nominal harus lebih besar dari 0.',
            'amount.regex' => 'Nominal maksimal 2 angka desimal (gunakan titik sebagai pemisah desimal).',
            'debit_account_id.exists' => 'Akun debit harus akun aktif & postable.',
            'credit_account_id.exists' => 'Akun kredit harus akun aktif & postable.',
            'credit_account_id.different' => 'Akun debit dan kredit tidak boleh sama.',
            'attachments.*.mimes' => 'Lampiran harus berupa PDF, JPG, atau PNG.',
            'attachments.*.mimetypes' => 'Lampiran harus berupa PDF, JPG, atau PNG.',
            'attachments.*.max' => 'Ukuran lampiran maksimal '.number_format(app(Settings::class)->get('attachment_max_kb') / 1024, 0).' MB per berkas.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'trx_date' => 'tanggal',
            'due_date' => 'jatuh tempo',
            'description' => 'deskripsi',
            'amount' => 'nominal',
            'debit_account_id' => 'akun debit',
            'credit_account_id' => 'akun kredit',
            'party_name' => 'pihak terkait',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Terima format "1.250.000" atau "1250000,50" dari input pengguna.
        $amount = (string) $this->input('amount', '');
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $amount)) {
            $amount = str_replace(['.', ','], ['', '.'], $amount);
        } elseif (preg_match('/^\d+,\d{1,2}$/', $amount)) {
            $amount = str_replace(',', '.', $amount);
        }
        $this->merge(['amount' => $amount]);
    }
}
