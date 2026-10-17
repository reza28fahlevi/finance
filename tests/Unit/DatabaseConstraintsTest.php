<?php

namespace Tests\Unit;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\FinanceFixtures;
use Tests\TestCase;

/**
 * Constraint penting di level database (RANCANGAN.md bagian 6) — berlaku walau aplikasi dilewati.
 */
class DatabaseConstraintsTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    private int $journalId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCore();
        $this->journalId = $this->manualJournal([['5-1000', '100', 0], ['1-1001', 0, '100']])->id;
    }

    private function insertLine(string $debit, string $credit): void
    {
        DB::table('journal_lines')->insert([
            'journal_id' => $this->journalId, 'line_no' => 9, 'account_id' => $this->account('5-1000')->id,
            'debit' => $debit, 'credit' => $credit, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_check_constraint_rejects_line_with_both_debit_and_credit(): void
    {
        $this->expectException(QueryException::class);
        $this->insertLine('10.00', '10.00');
    }

    public function test_check_constraint_rejects_negative_amount(): void
    {
        $this->expectException(QueryException::class);
        $this->insertLine('-10.00', '0');
    }

    public function test_check_constraint_rejects_zero_line(): void
    {
        $this->expectException(QueryException::class);
        $this->insertLine('0', '0');
    }

    public function test_valid_line_is_accepted(): void
    {
        $this->insertLine('10.00', '0');
        $this->assertDatabaseHas('journal_lines', ['journal_id' => $this->journalId, 'line_no' => 9]);
    }

    public function test_account_balance_is_unique_per_account_and_period(): void
    {
        $row = ['account_id' => $this->account('1-1001')->id, 'period_id' => $this->currentPeriod()->id, 'created_at' => now(), 'updated_at' => now()];
        DB::table('account_balances')->insert($row);

        $this->expectException(QueryException::class);
        DB::table('account_balances')->insert($row);
    }

    public function test_money_columns_are_decimal_18_2(): void
    {
        $columns = DB::select("SELECT TABLE_NAME, COLUMN_NAME, NUMERIC_PRECISION, NUMERIC_SCALE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE = 'decimal'");

        $this->assertNotEmpty($columns);
        foreach ($columns as $c) {
            $this->assertSame([18, 2], [(int) $c->NUMERIC_PRECISION, (int) $c->NUMERIC_SCALE], "{$c->TABLE_NAME}.{$c->COLUMN_NAME}");
        }
    }

    public function test_reference_numbers_are_unique(): void
    {
        $ref = DB::table('journal_entries')->where('id', $this->journalId)->value('ref_no');

        $this->expectException(QueryException::class);
        DB::table('journal_entries')->insert([
            'ref_no' => $ref, 'entry_date' => now()->toDateString(), 'period_id' => $this->currentPeriod()->id,
            'source' => 'MANUAL', 'status' => 'DRAFT', 'description' => 'dup', 'created_by' => $this->user('accountant')->id,
        ]);
    }
}
