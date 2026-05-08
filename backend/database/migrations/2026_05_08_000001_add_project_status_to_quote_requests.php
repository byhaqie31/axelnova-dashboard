<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->enum('project_status', ['pending', 'in_progress', 'delivered', 'completed'])
                ->nullable()
                ->after('status');
            $table->timestamp('project_started_at')->nullable()->after('project_status');
            $table->timestamp('project_delivered_at')->nullable()->after('project_started_at');
            $table->timestamp('project_completed_at')->nullable()->after('project_delivered_at');

            $table->index('project_status');
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropIndex(['project_status']);
            $table->dropColumn(['project_status', 'project_started_at', 'project_delivered_at', 'project_completed_at']);
        });
    }
};
