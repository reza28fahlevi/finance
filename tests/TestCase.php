<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase juga membungkus koneksi "sequence" (penomoran) dalam transaksi
     * sehingga nomor urut di-rollback antar test.
     *
     * @var list<string>
     */
    protected $connectionsToTransact = ['mysql', 'sequence'];
}
