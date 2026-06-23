<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();

            // Who is referring (so we can credit + pay them)
            $table->string('referrer_name', 150);
            $table->string('referrer_email', 200);
            $table->string('referrer_phone', 30)->nullable();

            // The business being referred
            $table->string('business_name', 200);
            $table->string('business_contact_name', 150)->nullable();
            $table->string('business_email', 200)->nullable();
            $table->string('business_phone', 30)->nullable();

            // Relationship tier drives the commission band (derived server-side).
            $table->enum('relationship_tier', ['cold', 'warm', 'closed'])->default('cold');
            $table->unsignedTinyInteger('commission_tier_pct'); // 5 | 10 | 15

            $table->text('notes')->nullable();

            $table->enum('status', ['new', 'contacted', 'qualified', 'converted', 'rejected'])
                ->default('new');
            $table->boolean('agreed_terms')->default(false);

            // Set when a referral converts into a paid engagement.
            $table->foreignId('linked_order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('referrer_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
