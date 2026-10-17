<?php

namespace Tests\Unit;

use App\Services\SequenceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SequenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private SequenceService $seq;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seq = app(SequenceService::class);
    }

    public function test_generates_incrementing_padded_numbers(): void
    {
        $date = Carbon::parse('2026-10-15');

        $this->assertSame('JV-2026-10-0001', $this->seq->next('JV-{YYYY}-{MM}-{SEQ:4}', $date));
        $this->assertSame('JV-2026-10-0002', $this->seq->next('JV-{YYYY}-{MM}-{SEQ:4}', $date));
        $this->assertSame('JV-2026-10-0003', $this->seq->next('JV-{YYYY}-{MM}-{SEQ:4}', $date));
    }

    public function test_sequence_resets_per_prefix_month(): void
    {
        $this->seq->next('JV-{YYYY}-{MM}-{SEQ:4}', Carbon::parse('2026-10-01'));
        $this->seq->next('JV-{YYYY}-{MM}-{SEQ:4}', Carbon::parse('2026-10-31'));

        $this->assertSame('JV-2026-11-0001', $this->seq->next('JV-{YYYY}-{MM}-{SEQ:4}', Carbon::parse('2026-11-01')));
        $this->assertSame('JV-2026-10-0003', $this->seq->next('JV-{YYYY}-{MM}-{SEQ:4}', Carbon::parse('2026-10-05')));
    }

    public function test_type_token_keeps_separate_counters(): void
    {
        $d = Carbon::parse('2026-10-10');

        $this->assertSame('BKK-2026-10-0001', $this->seq->next('{TYPE}-{YYYY}-{MM}-{SEQ:4}', $d, ['TYPE' => 'BKK']));
        $this->assertSame('BKM-2026-10-0001', $this->seq->next('{TYPE}-{YYYY}-{MM}-{SEQ:4}', $d, ['TYPE' => 'BKM']));
        $this->assertSame('BKK-2026-10-0002', $this->seq->next('{TYPE}-{YYYY}-{MM}-{SEQ:4}', $d, ['TYPE' => 'BKK']));
    }

    public function test_format_without_seq_token_gets_suffix_and_custom_padding(): void
    {
        $d = Carbon::parse('2026-10-10');

        $this->assertSame('X-0001', $this->seq->next('X', $d));
        $this->assertSame('INV26-000001', $this->seq->next('INV{YY}-{SEQ:6}', $d));
    }

    public function test_many_sequential_calls_never_duplicate(): void
    {
        $d = Carbon::parse('2026-10-10');
        $numbers = [];
        for ($i = 0; $i < 50; $i++) {
            $numbers[] = $this->seq->next('JV-{YYYY}-{MM}-{SEQ:4}', $d);
        }

        $this->assertCount(50, array_unique($numbers));
        $this->assertSame('JV-2026-10-0050', end($numbers));
        $this->assertDatabaseHas('sequences', ['key' => 'JV-2026-10-#', 'last_number' => 50], 'sequence');
    }
}
