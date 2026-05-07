<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 100);
            $table->string('icon', 80);
            $table->text('description');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_category_id')
                ->constrained('service_categories')
                ->cascadeOnDelete();
            $table->string('slug', 80);
            $table->string('name', 100);
            $table->string('tagline', 200);
            $table->decimal('price_min_myr', 12, 2);
            $table->decimal('price_max_myr', 12, 2)->nullable();
            $table->string('unit', 50);
            $table->string('duration_text', 50);
            $table->string('revisions', 50)->nullable();
            $table->boolean('featured')->default(false);
            $table->json('features');
            $table->string('cta', 100)->nullable();
            $table->json('quote_key')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['service_category_id', 'slug']);
            $table->index('active');
            $table->index('featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_packages');
        Schema::dropIfExists('service_categories');
    }
};
