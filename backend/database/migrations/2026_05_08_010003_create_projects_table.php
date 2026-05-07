<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 100);
            $table->string('description', 500);
            $table->text('long_description');
            $table->enum('status', ['live', 'soon', 'wip', 'planning'])->default('planning');
            $table->string('url', 500)->nullable();
            $table->string('repo', 500)->nullable();
            $table->json('tags');
            $table->json('stack');
            $table->boolean('featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('cover_image_url', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('active');
            $table->index('featured');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
