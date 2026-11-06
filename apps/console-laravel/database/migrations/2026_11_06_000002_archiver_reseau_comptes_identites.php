<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('reseau_comptes_identites', 'archive_reseau_comptes_identites');
    }

    public function down(): void
    {
        Schema::rename('archive_reseau_comptes_identites', 'reseau_comptes_identites');
    }
};
