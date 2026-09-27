<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The blog (see docs/global/BLOG.md). One row per article; `sections` is the
 * ordered list of {id, heading, body_md, image_url, image_alt, quote, quote_by}
 * — Markdown is the storage format, rendered to safe HTML on read by
 * App\Support\BlogMarkdown. Images are URLs only (no upload). Soft-deletes; the
 * slug unique index is absolute (it includes deleted rows), which is why
 * BlogPost::uniqueSlug() checks withTrashed().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 160);
            $table->text('excerpt');
            $table->json('sections');
            $table->string('cover_image_url', 500)->nullable();
            $table->string('cover_image_alt', 160)->nullable();
            $table->string('category', 60)->nullable();
            $table->json('tags');
            $table->string('cta_heading', 120)->nullable();
            $table->text('cta_body')->nullable();
            $table->string('cta_label', 60)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 160)->nullable();
            $table->unsignedSmallInteger('reading_minutes')->default(1);
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
