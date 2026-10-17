<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\PeriodStatus;
use App\Enums\Role;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalTemplate;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Database\Seeder;

/**
 * Data inti: pengguna per peran, Chart of Accounts, periode berjalan (OPEN),
 * template jurnal otomatis, dan pengaturan umum default.
 */
class CoreSeeder extends Seeder
{
    public const DEFAULT_PASSWORD = 'password';

    public function run(): void
    {
        $this->seedUsers();
        $this->seedAccounts();
        $this->seedPeriods();
        $this->seedTemplates();
        $this->seedSettings();
    }

    private function seedUsers(): void
    {
        $users = [
            ['Administrator Sistem', 'admin@finance.test', Role::Admin],
            ['Budi Santoso', 'staff@finance.test', Role::Staff],
            ['Andi Nugroho', 'staff2@finance.test', Role::Staff],
            ['Rina Supervisor', 'supervisor@finance.test', Role::Supervisor],
            ['Siti Rahma, S.E.', 'manager@finance.test', Role::Manager],
            ['Ahmad Faisal, Ak.', 'akuntan@finance.test', Role::Accountant],
            ['Dewi Auditor', 'auditor@finance.test', Role::Auditor],
        ];

        foreach ($users as [$name, $email, $role]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => self::DEFAULT_PASSWORD,
                'role' => $role,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }
    }

    private function seedAccounts(): void
    {
        // [kode, nama, tipe, postable, kas, parent]
        $accounts = [
            ['1-0000', 'Aset Lancar', AccountType::Asset, false, false, null],
            ['1-1000', 'Kas & Setara Kas', AccountType::Asset, false, false, '1-0000'],
            ['1-1001', 'Kas Operasional (BCA)', AccountType::Asset, true, true, '1-1000'],
            ['1-1002', 'Kas Bank Mandiri', AccountType::Asset, true, true, '1-1000'],
            ['1-1003', 'Kas Kecil (Petty Cash)', AccountType::Asset, true, true, '1-1000'],
            ['1-2000', 'Piutang Usaha', AccountType::Asset, true, false, '1-0000'],
            ['1-3000', 'Persediaan', AccountType::Asset, true, false, '1-0000'],
            ['1-4000', 'Biaya Dibayar Dimuka', AccountType::Asset, true, false, '1-0000'],
            ['2-0000', 'Kewajiban Lancar', AccountType::Liability, false, false, null],
            ['2-1000', 'Utang Usaha', AccountType::Liability, true, false, '2-0000'],
            ['2-2000', 'Beban Akrual', AccountType::Liability, true, false, '2-0000'],
            ['3-0000', 'Ekuitas & Modal', AccountType::Equity, false, false, null],
            ['3-1000', 'Modal Disetor', AccountType::Equity, true, false, '3-0000'],
            ['3-2000', 'Laba Ditahan', AccountType::Equity, true, false, '3-0000'],
            ['4-0000', 'Pendapatan Operasional', AccountType::Revenue, false, false, null],
            ['4-1000', 'Pendapatan Penjualan', AccountType::Revenue, true, false, '4-0000'],
            ['4-2000', 'Pendapatan Jasa', AccountType::Revenue, true, false, '4-0000'],
            ['5-0000', 'Beban Operasional', AccountType::Expense, false, false, null],
            ['5-1000', 'Beban Operasional Umum', AccountType::Expense, true, false, '5-0000'],
            ['5-1100', 'Beban Gaji & Upah Karyawan', AccountType::Expense, true, false, '5-0000'],
            ['5-1200', 'Beban Sewa & Fasilitas Kantor', AccountType::Expense, true, false, '5-0000'],
            ['5-1300', 'Beban Utilitas & Internet', AccountType::Expense, true, false, '5-0000'],
            ['5-1400', 'Beban ATK & Perlengkapan', AccountType::Expense, true, false, '5-0000'],
        ];

        foreach ($accounts as [$code, $name, $type, $postable, $cash, $parent]) {
            Account::updateOrCreate(['code' => $code], [
                'name' => $name,
                'type' => $type,
                'normal_balance' => $type->defaultNormalBalance(),
                'parent_id' => $parent ? Account::where('code', $parent)->value('id') : null,
                'is_postable' => $postable,
                'is_active' => true,
                'is_cash' => $cash,
            ]);
        }
    }

    /** Periode bulan berjalan (OPEN) beserta 3 bulan sebelumnya (OPEN; DemoSeeder akan menutupnya). */
    private function seedPeriods(): void
    {
        for ($i = 3; $i >= 0; $i--) {
            $start = now()->startOfMonth()->subMonthsNoOverflow($i);
            AccountingPeriod::firstOrCreate(['code' => $start->format('Y-m')], [
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->endOfMonth()->toDateString(),
                'status' => PeriodStatus::Open,
            ]);
        }
    }

    private function seedTemplates(): void
    {
        $id = fn (string $code) => Account::where('code', $code)->value('id');

        $templates = [
            TransactionType::CashIn->value => ['1-1001', '4-1000'],          // Kas | Pendapatan
            TransactionType::CashOut->value => ['5-1000', '1-1001'],         // Beban | Kas
            TransactionType::SalesInvoice->value => ['1-2000', '4-1000'],    // Piutang | Pendapatan
            TransactionType::PurchaseInvoice->value => ['5-1000', '2-1000'], // Beban | Utang
        ];

        foreach ($templates as $type => [$debit, $credit]) {
            JournalTemplate::updateOrCreate(['transaction_type' => $type], [
                'debit_account_id' => $id($debit),
                'credit_account_id' => $id($credit),
            ]);
        }
    }

    private function seedSettings(): void
    {
        $settings = app(Settings::class);
        $settings->flush();
        $settings->set($settings->all()); // simpan default dari config ke tabel settings
    }
}
