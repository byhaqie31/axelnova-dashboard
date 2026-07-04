<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 6 — Announcements. A company notice authored from the cockpit.
 * `audience` scopes visibility once published: 'team' (workspace only),
 * 'partners' (a forward hook for a later phase — the partner portal doesn't
 * read this table yet), or 'all' (both). `published_at` null = draft; the
 * team feed (App\Http\Controllers\Api\V1\Team\AnnouncementsController) only
 * ever reads published rows where audience is 'team' or 'all'.
 *
 * No soft-deletes (the brief's schema carries none) and no delete endpoint —
 * "unpublish" (clearing `published_at` back to null) is the only retraction
 * verb, enforced entirely in the admin controller's `update()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();

            $table->string('title', 200);
            $table->text('body');
            $table->enum('audience', ['team', 'partners', 'all'])->default('team');
            $table->timestamp('published_at')->nullable();

            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
