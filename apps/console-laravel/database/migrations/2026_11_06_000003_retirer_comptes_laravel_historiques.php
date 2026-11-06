<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['archive_reseau_comptes_identites', 'password_reset_tokens', 'users'];

        foreach ($tables as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException("Le retrait exige une table historique vide ({$table}).");
            }
        }

        foreach ($tables as $table) {
            Schema::drop($table);
        }
    }

    public function down(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        (require __DIR__.'/2026_11_06_000001_create_reseau_comptes_identites_table.php')->up();
        (require __DIR__.'/2026_11_06_000002_archiver_reseau_comptes_identites.php')->up();
    }
};
