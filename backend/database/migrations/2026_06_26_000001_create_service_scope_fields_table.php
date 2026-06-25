<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-managed quote scope fields. Supersedes the hardcoded scope blocks
        // in QuoteScopeFields.vue and the pricing_configs JSON `modifiers` map
        // (which stays as a legacy fallback): each row defines a builder input
        // (slider | select | toggle) AND its per-package pricing.
        Schema::create('service_scope_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_category_id')
                ->constrained('service_categories')
                ->cascadeOnDelete();
            $table->string('field_key', 80);
            $table->string('label', 150);
            $table->string('type', 16); // slider | select | toggle
            $table->json('applies_to'); // quote_key.package strings; [] = all packages
            $table->json('config');     // type-specific (min/max/threshold/price, options, …)
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['service_category_id', 'field_key']);
            $table->index(['service_category_id', 'active']);
        });

        // NOTE: intentionally does NOT auto-seed. Production's catalog has diverged
        // from the original seeder's category slugs, so blindly seeding could attach
        // legacy fields to the wrong (renamed) category. Scope fields are added per
        // category via /admin/services. ServiceScopeFieldsSeeder still runs for
        // fresh local/dev installs via DatabaseSeeder, and can be run on demand
        // (`php artisan db:seed --class=ServiceScopeFieldsSeeder`) where slugs match.
    }

    public function down(): void
    {
        Schema::dropIfExists('service_scope_fields');
    }
};
