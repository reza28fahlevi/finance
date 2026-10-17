<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * CoreSeeder selalu dijalankan. DemoSeeder (data contoh transaksi/jurnal) hanya di luar production;
     * jalankan manual dengan: php artisan db:seed --class=DemoSeeder
     */
    public function run(): void
    {
        $this->call(CoreSeeder::class);

        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
