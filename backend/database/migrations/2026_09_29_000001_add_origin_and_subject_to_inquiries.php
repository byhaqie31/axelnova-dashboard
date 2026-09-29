<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The /contact form now lands in the same inquiries pipeline as /quote. `origin`
 * records which public form it came from (existing rows are all /quote, hence the
 * default); `subject` carries the contact form's subject pill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->enum('origin', ['quote', 'contact'])->default('quote')->after('source');
            $table->string('subject', 60)->nullable()->after('origin');

            $table->index('origin');
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropIndex(['origin']);
            $table->dropColumn(['origin', 'subject']);
        });
    }
};
