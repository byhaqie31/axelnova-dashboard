<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-built quotations may reference a client without a phone on file.
        DB::statement('ALTER TABLE quotations MODIFY phone VARCHAR(30) NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE quotations SET phone = '' WHERE phone IS NULL");
        DB::statement('ALTER TABLE quotations MODIFY phone VARCHAR(30) NOT NULL');
    }
};
