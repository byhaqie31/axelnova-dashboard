<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive indexes for the admin list endpoints: every admin list sorts or
 * filters on these columns (`latest('created_at')`, `latest('issued_at')`,
 * overdue checks, analytics roll-ups), which previously fell back to full
 * scans + filesorts once the tables grew.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->index('created_at');
            $table->index(['status', 'created_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index('issued_at');
            $table->index('due_at');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('paid_at');
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('feedback', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->index('name');
        });

        Schema::table('page_views', function (Blueprint $table) {
            $table->index('referrer');
            $table->index('ip_hash');
        });

        Schema::table('quotations', function (Blueprint $table) {
            // expireOverdue(): WHERE status = 'sent' AND expires_at < NOW().
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['issued_at']);
            $table->dropIndex(['due_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['paid_at']);
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('feedback', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });

        Schema::table('page_views', function (Blueprint $table) {
            $table->dropIndex(['referrer']);
            $table->dropIndex(['ip_hash']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex(['status', 'expires_at']);
        });
    }
};
