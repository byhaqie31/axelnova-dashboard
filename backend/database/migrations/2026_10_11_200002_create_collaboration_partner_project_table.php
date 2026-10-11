<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A project built with a studio partner. `mode` is how the studios worked
 * together; `credit_public` decides whether the site names the partner on it
 * (subcontracted work is often confidential, so only co-builds default on —
 * see CollaborationPartner::linkProject()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaboration_partner_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collaboration_partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->enum('mode', ['co_build', 'subcontract', 'referral']);
            $table->string('role_note', 200)->nullable();
            $table->boolean('credit_public')->default(false);
            $table->timestamps();

            $table->unique(['collaboration_partner_id', 'project_id'], 'collab_partner_project_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaboration_partner_project');
    }
};
