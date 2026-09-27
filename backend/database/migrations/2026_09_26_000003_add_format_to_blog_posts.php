<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editorial format for a blog post — the accent eyebrow before the date on the
 * article page ("GUIDE · 18 September 2026"). One of BlogPost::FORMATS
 * (article | guide | tutorial | case_study | opinion | news); every existing
 * row backfills to `article` via the default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('format', 24)->default('article')->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn('format');
        });
    }
};
