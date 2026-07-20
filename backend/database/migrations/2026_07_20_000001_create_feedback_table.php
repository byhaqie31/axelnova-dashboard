<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feedback & Reviews — one row per client review. Anchored to an order
 * (`order_id` unique, nullable so admin can log standalone feedback received
 * outside the pipeline); `client_id` is denormalised for list display. The
 * client fills scores via the token-gated /feedback/{token} page; nothing
 * reaches the public testimonial wall until an admin publishes it AND the
 * client granted `publish_consent`. See docs/global/FEEDBACK-MODULE.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();

            $table->string('reference_code')->unique();          // AXNF-YYYY-NNNN (ReferenceCodeGenerator)
            $table->foreignId('order_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('public_token', 48)->unique();        // token-gated public page

            $table->string('name')->nullable();                  // snapshot for display
            $table->string('email')->nullable();
            $table->string('project_label')->nullable();         // e.g. "Roofly.my engagement"

            $table->unsignedTinyInteger('overall')->nullable();              // 1–5
            $table->unsignedTinyInteger('rating_design')->nullable();        // 1–5
            $table->unsignedTinyInteger('rating_communication')->nullable(); // 1–5
            $table->unsignedTinyInteger('rating_delivery')->nullable();      // 1–5
            $table->unsignedTinyInteger('rating_value')->nullable();         // 1–5
            $table->unsignedTinyInteger('nps')->nullable();                  // 0–10

            $table->text('praise')->nullable();                  // "what we got right"
            $table->text('improve')->nullable();                 // "where to improve"

            $table->boolean('publish_consent')->default(false);
            $table->string('attribution_name')->nullable();      // shown on the wall
            $table->string('attribution_role')->nullable();

            $table->enum('status', ['pending', 'approved', 'published', 'archived'])->default('pending');
            $table->enum('source', ['self_serve', 'admin'])->default('self_serve');
            $table->boolean('featured')->default(false);         // pin on wall
            $table->integer('sort_order')->default(0);           // wall ordering (SortOrder helper)

            $table->timestamp('submitted_at')->nullable();       // set on client submit; locks resubmission
            $table->timestamp('reviewed_at')->nullable();        // stamped on first admin open
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
