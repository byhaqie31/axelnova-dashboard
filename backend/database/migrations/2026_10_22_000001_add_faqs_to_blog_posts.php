<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FAQ pairs on a blog post — 0–8 {question, answer} rows (BlogPost::FAQ_MAX),
 * rendered as a "Frequently asked questions" block at the end of the article
 * and emitted as FAQPage JSON-LD for search engines and AI answers (see
 * docs/global/BLOG.md). Nullable so existing rows need no backfill: the model
 * reads null as an empty list (BlogPost::faqList) and the resources never
 * serve null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->json('faqs')->nullable()->after('sections');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn('faqs');
        });
    }
};
