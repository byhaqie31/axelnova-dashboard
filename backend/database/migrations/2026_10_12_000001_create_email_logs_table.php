<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per email that left (or definitively failed to leave) the
     * dashboard — spec docs/superpowers/specs/2026-10-11-email-foundation-design.md §5.2.
     * Written by the mail-event listeners; status advanced by the Resend
     * webhook. `related_type` is a short alias (EmailLog::RELATED), like the
     * activity log's short subject_type. No email bodies are stored.
     */
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64);
            $table->string('audience', 16);
            $table->string('related_type', 40)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('to_email');
            $table->string('to_name')->nullable();
            $table->string('subject')->nullable();
            $table->string('route', 32)->nullable();
            $table->string('status', 16)->default('sent');
            $table->text('status_detail')->nullable();
            $table->string('message_id')->nullable();
            $table->string('provider_id', 64)->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('alerted_at')->nullable();
            $table->timestamps();

            $table->index(['related_type', 'related_id']);
            $table->index('to_email');
            $table->index('message_id');
            $table->index('provider_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
