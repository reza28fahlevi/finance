<?php

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 20);
            $table->string('permission', 50);
            $table->timestamps();

            $table->unique(['role', 'permission']);
        });

        // Isi dengan matriks default (RANCANGAN.md 10.4) agar perilaku tidak berubah setelah migrasi.
        $rows = [];
        foreach (Role::cases() as $role) {
            foreach ($role->defaultPermissions() as $permission) {
                $rows[] = ['role' => $role->value, 'permission' => $permission, 'created_at' => now(), 'updated_at' => now()];
            }
        }
        DB::table('role_permissions')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
