<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public static function normalizeCases(): array
    {
        return [
            'integer' => [1500, '1500.00'],
            'string desimal' => ['1250000.5', '1250000.50'],
            'null' => [null, '0.00'],
            'kosong' => ['', '0.00'],
            'negatif' => ['-10', '-10.00'],
            'presisi besar' => ['9999999999999999.99', '9999999999999999.99'],
        ];
    }

    #[DataProvider('normalizeCases')]
    public function test_normalize(mixed $input, string $expected): void
    {
        $this->assertSame($expected, Money::normalize($input));
    }

    public function test_normalize_rejects_non_numeric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::normalize('12abc');
    }

    public function test_arithmetic_is_exact_without_float_errors(): void
    {
        // 0.1 + 0.2 dengan float = 0.30000000000000004
        $this->assertSame('0.30', Money::add('0.10', '0.20'));
        $this->assertSame(0, Money::cmp(Money::add('0.10', '0.20'), '0.30'));
        $this->assertSame('-0.05', Money::sub('0.10', '0.15'));
    }

    public function test_comparisons(): void
    {
        $this->assertTrue(Money::isPositive('0.01'));
        $this->assertFalse(Money::isPositive('0.00'));
        $this->assertTrue(Money::isZero('0'));
        $this->assertSame('12.50', Money::abs('-12.50'));
    }

    public function test_format_rupiah(): void
    {
        $this->assertSame('Rp 1.250.000', Money::format('1250000.00'));
        $this->assertSame('Rp 1.250.000,50', Money::format('1250000.50'));
        $this->assertSame('-Rp 5.000', Money::format('-5000'));
        $this->assertSame('15.000.000', Money::format(15000000, false));
    }
}
