<?php

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalTemplate;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\JournalService;
use App\Services\PeriodService;
use App\Services\PostingService;
use App\Services\TransactionService;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

/**
 * Data demo yang dibuat melalui service (bukan insert langsung) agar konsisten:
 * nomor sequence, audit log, jurnal, posting GL, dan saldo akun semuanya valid.
 */
class DemoSeeder extends Seeder
{
    public function __construct(
        private TransactionService $transactions,
        private ApprovalService $approvals,
        private JournalService $journals,
        private PostingService $posting,
        private PeriodService $periods,
    ) {}

    public function run(): void
    {
        $staff = User::where('email', 'staff@finance.test')->firstOrFail();
        $staff2 = User::where('email', 'staff2@finance.test')->firstOrFail();
        $manager = User::where('email', 'manager@finance.test')->firstOrFail();
        $supervisor = User::where('email', 'supervisor@finance.test')->firstOrFail();
        $accountant = User::where('email', 'akuntan@finance.test')->firstOrFail();
        $acc = fn (string $code) => Account::where('code', $code)->value('id');

        $prevMonth = now()->startOfMonth()->subMonthNoOverflow();
        $day = fn (int $d) => Carbon::create(now()->year, now()->month, min($d, now()->day));

        // ---- Periode sebelumnya: setoran modal awal + beberapa transaksi, lalu tutup buku ----
        $this->as($accountant);
        $opening = $this->journals->createManual([
            'entry_date' => $prevMonth->copy()->toDateString(),
            'description' => 'Setoran modal awal pemegang saham',
            'lines' => [
                ['account_id' => $acc('1-1001'), 'description' => 'Setoran ke BCA', 'debit' => '500000000', 'credit' => 0],
                ['account_id' => $acc('1-1002'), 'description' => 'Setoran ke Mandiri', 'debit' => '300000000', 'credit' => 0],
                ['account_id' => $acc('1-1003'), 'description' => 'Kas kecil', 'debit' => '20000000', 'credit' => 0],
                ['account_id' => $acc('3-1000'), 'description' => 'Modal disetor', 'debit' => 0, 'credit' => '820000000'],
            ],
        ], $accountant);
        $this->posting->post($opening, $accountant);

        $this->flow($staff, $manager, $accountant, TransactionType::CashOut, $prevMonth->copy()->addDays(9), 'Pembayaran gaji karyawan bulan '.$prevMonth->translatedFormat('F'), '85000000', [
            'debit_account_id' => $acc('5-1100'), 'credit_account_id' => $acc('1-1001'), 'party_name' => 'Karyawan',
        ], post: true);
        $this->flow($staff, $supervisor, $accountant, TransactionType::CashIn, $prevMonth->copy()->addDays(14), 'Pendapatan jasa konsultasi proyek implementasi', '120000000', [
            'debit_account_id' => $acc('1-1001'), 'credit_account_id' => $acc('4-2000'), 'party_name' => 'PT Global Teknologi',
        ], post: true);

        $this->as($accountant);
        foreach (AccountingPeriod::where('start_date', '<', now()->startOfMonth()->toDateString())->orderBy('start_date')->get() as $period) {
            $this->periods->close($period, $accountant);
        }

        // ---- Periode berjalan ----
        $this->flow($staff, $manager, $accountant, TransactionType::CashIn, $day(5), 'Pelunasan termin II kontrak pemeliharaan sistem', '150000000', [
            'debit_account_id' => $acc('1-1001'), 'credit_account_id' => $acc('4-2000'), 'party_name' => 'PT Sinar Makmur Sentosa', 'reference' => 'INV/'.now()->format('Y/m').'/891',
        ], post: true);
        $this->flow($staff2, $supervisor, $accountant, TransactionType::CashOut, $day(7), 'Pembayaran tagihan listrik & internet kantor', '6500000', [
            'debit_account_id' => $acc('5-1300'), 'credit_account_id' => $acc('1-1002'), 'party_name' => 'PLN & ISP',
        ], post: true);

        // Approved → jurnal otomatis Draft (antrean Posting Batch)
        $this->flow($staff, $manager, null, TransactionType::SalesInvoice, $day(8), 'Tagihan jasa implementasi modul akuntansi', '75000000', [
            'debit_account_id' => $acc('1-2000'), 'credit_account_id' => $acc('4-2000'), 'party_name' => 'PT Graha Nusantara Sukses', 'due_date' => $day(8)->copy()->addDays(30)->toDateString(),
        ]);
        $this->flow($staff2, $supervisor, null, TransactionType::CashOut, $day(9), 'Reimbursement BBM & tol armada operasional', '3250000', [
            'debit_account_id' => $acc('5-1000'), 'credit_account_id' => $acc('1-1003'), 'party_name' => 'Andi Kurniawan (HRD)',
        ]);

        // Pending review (inbox approval)
        $this->flow($staff, null, null, TransactionType::CashOut, $day(10), 'Pembayaran sewa kantor Q4', '45000000', [
            'debit_account_id' => $acc('5-1200'), 'credit_account_id' => $acc('1-1002'), 'party_name' => 'PT Graha Sentosa',
        ], attachment: 'Bukti_Invoice_Sewa_Q4.pdf');
        $this->flow($staff2, null, null, TransactionType::PurchaseInvoice, $day(11), 'Pengadaan perangkat IT operasional', '21200000', [
            'debit_account_id' => $acc('1-3000'), 'credit_account_id' => $acc('2-1000'), 'party_name' => 'PT Citra Media', 'due_date' => $day(11)->copy()->addDays(30)->toDateString(),
        ], attachment: 'Faktur_PT_Citra_Media.pdf');
        $this->flow($staff, null, null, TransactionType::CashIn, $day(12), 'Pelunasan piutang client Alpha', '8000000', [
            'debit_account_id' => $acc('1-1001'), 'credit_account_id' => $acc('1-2000'), 'party_name' => 'Alpha Corp',
        ]);

        // Rejected (perlu revisi)
        $rejected = $this->flow($staff, null, null, TransactionType::CashOut, $day(12), 'Jamuan makan malam klien', '2350000', [
            'debit_account_id' => $acc('5-1000'), 'credit_account_id' => $acc('1-1003'), 'party_name' => 'Restoran Nusantara',
        ]);
        $this->as($manager);
        $this->approvals->reject($rejected, $manager, 'Nominal pada form tidak sesuai dengan struk terlampir; mohon dikoreksi.');

        // Draft & cancelled
        $this->as($staff);
        $this->transactions->create(TransactionType::CashOut, [
            'trx_date' => $day(13)->toDateString(), 'description' => 'Pembelian kertas HVS & tinta printer', 'amount' => '1200000',
            'debit_account_id' => $acc('5-1400'), 'credit_account_id' => $acc('1-1003'), 'party_name' => 'Toko ATK Bintang Jaya',
        ], $staff);
        $cancel = $this->transactions->create(TransactionType::CashOut, [
            'trx_date' => $day(13)->toDateString(), 'description' => 'Langganan majalah (dobel input)', 'amount' => '500000',
            'debit_account_id' => $acc('5-1000'), 'credit_account_id' => $acc('1-1003'),
        ], $staff);
        $this->transactions->cancel($cancel, 'Input ganda');

        // Jurnal manual draft (penyesuaian akrual)
        $this->as($accountant);
        $this->journals->createManual([
            'entry_date' => $day(14)->toDateString(),
            'description' => 'Penyesuaian akrual beban utilitas yang belum ditagih',
            'lines' => [
                ['account_id' => $acc('5-1300'), 'description' => 'Akrual utilitas', 'debit' => '3500000', 'credit' => 0],
                ['account_id' => $acc('2-2000'), 'description' => 'Beban akrual', 'debit' => 0, 'credit' => '3500000'],
            ],
        ], $accountant);

        Auth::logout();
    }

    /**
     * Buat transaksi (maker), opsional submit/approve (checker) dan posting (akuntan).
     *
     * @param  array<string, mixed>  $extra
     */
    private function flow(User $maker, ?User $checker, ?User $poster, TransactionType $type, Carbon $date, string $description, string $amount, array $extra, bool $post = false, ?string $attachment = null): Transaction
    {
        $this->as($maker);
        $template = JournalTemplate::forType($type);
        $files = $attachment ? [$this->fakePdf($attachment)] : [];

        $trx = $this->transactions->create($type, array_merge([
            'trx_date' => $date->toDateString(),
            'description' => $description,
            'amount' => $amount,
            'debit_account_id' => $template->debit_account_id,
            'credit_account_id' => $template->credit_account_id,
        ], $extra), $maker, $files);

        if ($files === [] && bccomp($amount, (string) app(Settings::class)->get('attachment_required_above'), 2) === 1) {
            $this->transactions->update($trx, array_merge($trx->only(['description', 'amount', 'debit_account_id', 'credit_account_id', 'party_name', 'reference']), [
                'trx_date' => $trx->trx_date->toDateString(),
                'due_date' => $trx->due_date?->toDateString(),
            ]), $maker, [$this->fakePdf('Bukti_'.$trx->number.'.pdf')]);
        }

        $this->transactions->submit($trx, $maker);

        if ($checker) {
            $this->as($checker);
            ['journal' => $journal] = $this->approvals->approve($trx, $checker, 'Dokumen lengkap, sesuai anggaran.');

            if ($post && $poster) {
                $this->as($poster);
                $this->posting->post($journal, $poster);
            }
        }

        return $trx->fresh();
    }

    private function as(User $user): void
    {
        Auth::setUser($user);
    }

    private function fakePdf(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'demo').'.pdf';
        file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }
}
