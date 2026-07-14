<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `inputs` stores the validated issue-form fields (amount, type, discounts,
     * promo, notes…) so an invoice can be re-edited with full fidelity — editing
     * re-runs DocumentMapper over these inputs and re-freezes the payload.
     * `emailed_at` / `emailed_to` track the last send to the client.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('inputs')->nullable()->after('payload');
            $table->timestamp('emailed_at')->nullable()->after('due_at');
            $table->string('emailed_to')->nullable()->after('emailed_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['inputs', 'emailed_at', 'emailed_to']);
        });
    }
};
