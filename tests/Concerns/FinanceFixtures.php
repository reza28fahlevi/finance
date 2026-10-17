<?php

namespace Tests\Concerns;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\JournalTemplate;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\JournalService;
use App\Services\PostingService;
use App\Services\TransactionService;
use App\Support\Money;
use App\Support\Settings;
use Database\Seeders\CoreSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Data uji bersama: memakai CoreSeeder (user per peran, COA, periode OPEN, template).
 */
trait FinanceFixtures
{
    protected function seedCore(): void
    {
        Storage::fake('local');
        $this->seed(CoreSeeder::class);
        app(Settings::class)->flush();
    }

    protected function user(string $alias): User
    {
        $email = [
            'admin' => 'admin@finance.test',
            'staff' => 'staff@finance.test',
            'staff2' => 'staff2@finance.test',
            'supervisor' => 'supervisor@finance.test',
            'manager' => 'manager@finance.test',
            'accountant' => 'akuntan@finance.test',
            'auditor' => 'auditor@finance.test',
        ][$alias];

        return User::where('email', $email)->firstOrFail();
    }

    protected function account(string $code): Account
    {
        return Account::where('code', $code)->firstOrFail();
    }

    protected function currentPeriod(): AccountingPeriod
    {
        return AccountingPeriod::forDate(now());
    }

    protected function setting(string $key, mixed $value): void
    {
        app(Settings::class)->set([$key => $value]);
    }

    /** Buat transaksi DRAFT melalui service (nomor sequence & audit log tercatat). */
    protected function makeTransaction(?User $maker = null, TransactionType $type = TransactionType::CashOut, string $amount = '500000', array $attrs = [], array $files = []): Transaction
    {
        $maker ??= $this->user('staff');
        Auth::setUser($maker);
        $template = JournalTemplate::forType($type);

        return app(TransactionService::class)->create($type, array_merge([
            'trx_date' => now()->toDateString(),
            'description' => 'Transaksi uji '.$type->label(),
            'amount' => $amount,
            'debit_account_id' => $template->debit_account_id,
            'credit_account_id' => $template->credit_account_id,
        ], $attrs), $maker, $files);
    }

    protected function submittedTransaction(?User $maker = null, TransactionType $type = TransactionType::CashOut, string $amount = '500000', array $attrs = []): Transaction
    {
        $maker ??= $this->user('staff');
        // Lampirkan bukti otomatis bila nominal melewati ambang wajib lampiran.
        $needsFile = Money::cmp(Money::normalize($amount), app(Settings::class)->get('attachment_required_above')) === 1;
        $trx = $this->makeTransaction($maker, $type, $amount, $attrs, $needsFile ? [$this->fakePdf()] : []);

        return app(TransactionService::class)->submit($trx, $maker);
    }

    /** Transaksi yang sudah di-approve (jurnal otomatis DRAFT). */
    protected function approvedJournal(string $amount = '500000', TransactionType $type = TransactionType::CashOut): JournalEntry
    {
        $trx = $this->submittedTransaction(null, $type, $amount);
        $checker = $this->user('manager');
        Auth::setUser($checker);

        return app(ApprovalService::class)->approve($trx, $checker)['journal'];
    }

    /**
     * Jurnal manual DRAFT. $lines: [[kodeAkun, debit, kredit], ...]
     *
     * @param  list<array{0:string,1:string|int,2:string|int}>  $lines
     */
    protected function manualJournal(array $lines, ?string $date = null, string $description = 'Jurnal uji'): JournalEntry
    {
        $accountant = $this->user('accountant');
        Auth::setUser($accountant);

        return app(JournalService::class)->createManual([
            'entry_date' => $date ?? now()->toDateString(),
            'description' => $description,
            'lines' => array_map(fn ($l) => ['account_id' => $this->account($l[0])->id, 'debit' => $l[1], 'credit' => $l[2]], $lines),
        ], $accountant);
    }

    protected function postedJournal(array $lines, ?string $date = null): JournalEntry
    {
        $journal = $this->manualJournal($lines, $date);

        return app(PostingService::class)->post($journal, $this->user('accountant'));
    }

    protected function fakePdf(string $name = 'bukti.pdf', int $kilobytes = 0): UploadedFile
    {
        $content = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
        if ($kilobytes > 0) {
            $content .= str_repeat('A', $kilobytes * 1024);
        }

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    /** Berkas upload "asli" (MIME dideteksi dari isi berkas, bukan dari ekstensi). */
    protected function realUpload(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }
}
