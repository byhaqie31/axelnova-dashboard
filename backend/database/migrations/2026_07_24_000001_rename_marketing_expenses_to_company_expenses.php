<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The expense tracker was always general company spend, not just marketing —
 * rename the table to match (model MarketingExpense → CompanyExpense renames
 * with it). Pure rename: no columns or data touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('marketing_expenses', 'company_expenses');
    }

    public function down(): void
    {
        Schema::rename('company_expenses', 'marketing_expenses');
    }
};
