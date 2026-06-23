<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);
            $table->string('email', 200);
            $table->string('phone', 30)->nullable();
            $table->string('company', 200)->nullable();

            // Lightweight intent hints — the admin prices the real quote later.
            $table->string('project_type', 60)->nullable();
            $table->string('budget_hint', 100)->nullable();
            $table->string('timeline_hint', 100)->nullable();
            $table->text('message');

            $table->enum('source', ['web', 'referral', 'other'])->default('web');
            $table->enum('status', ['new', 'reviewing', 'quoted', 'archived'])->default('new');

            // Set when the admin builds a quotation off this inquiry.
            $table->foreignId('quotation_id')
                ->nullable()
                ->constrained('quotations')
                ->nullOnDelete();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
