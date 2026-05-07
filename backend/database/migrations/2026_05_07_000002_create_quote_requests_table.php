<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 20)->unique();
            $table->string('name', 150);
            $table->string('email', 200);
            $table->string('phone', 30);
            $table->string('company', 200)->nullable();
            $table->unsignedBigInteger('service_category_id')->nullable();
            $table->unsignedBigInteger('service_package_id')->nullable();
            $table->foreignId('pricing_config_id')
                ->constrained('pricing_configs')
                ->restrictOnDelete();
            $table->json('form_payload');
            $table->decimal('estimate_min_myr', 12, 2);
            $table->decimal('estimate_max_myr', 12, 2);
            $table->unsignedTinyInteger('estimate_weeks');
            $table->enum('status', ['new', 'viewed', 'contacted', 'converted', 'rejected', 'spam'])
                ->default('new');
            $table->string('pdf_path', 500)->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('quotation_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('viewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('email');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};
