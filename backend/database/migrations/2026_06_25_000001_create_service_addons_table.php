<?php

use Database\Seeders\ServiceAddonsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-managed quote add-ons. Replaces the hardcoded `addons` map in
        // pricing_configs JSON: a DB row claims its key (the JSON entry becomes a
        // legacy fallback). `addon_key` matches quotation_addons.addon_key and the
        // config map key the quote builder selects by.
        Schema::create('service_addons', function (Blueprint $table) {
            $table->id();
            $table->string('addon_key', 80)->unique();
            $table->string('label', 150);
            $table->decimal('amount_myr', 12, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('active');
        });

        // Promote the existing hardcoded add-ons immediately (prod auto-runs
        // migrations but not seeders) so the admin list and the builder agree on
        // deploy. Idempotent — re-running the seeder via db:seed is a no-op.
        (new ServiceAddonsSeeder())->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('service_addons');
    }
};
