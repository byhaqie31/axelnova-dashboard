<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Studio partners shown on /collaborations — the studios Axel Nova co-builds,
 * subcontracts or swaps referrals with. Distinct from `referral_partners`
 * (the commission programme behind /partners). Logos are static files under
 * frontend/public, stored as paths like projects.cover_image_url.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaboration_partners', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 100);
            $table->string('tagline', 150)->nullable();
            $table->string('summary', 200);
            $table->text('story')->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->string('symbol_url', 500)->nullable();
            $table->string('website_url', 500)->nullable();
            $table->string('founder_name', 100)->nullable();
            $table->string('founder_role', 100)->nullable();
            $table->string('location', 100)->nullable();
            $table->json('services');
            $table->date('partner_since')->nullable();
            $table->boolean('active')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaboration_partners');
    }
};
