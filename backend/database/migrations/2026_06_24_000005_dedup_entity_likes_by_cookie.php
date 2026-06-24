<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Switch like dedupe from hashed-IP to the per-browser cookie_id.
 *
 * IP-based dedupe collapsed every visitor behind a shared public IP (home WiFi,
 * office NAT, mobile CGNAT) into a single identity, so a second person on the
 * same network "toggled off" the first person's like instead of adding their own.
 * The frontend already sends a stable cookie_id; this makes it the dedupe key.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Give legacy rows (liked before cookie_id was captured) a stable, unique
        // identity so they survive the new constraint and keep counting.
        DB::table('entity_likes')->whereNull('cookie_id')->update([
            'cookie_id' => DB::raw("CONCAT('legacy-', id)"),
        ]);

        // Defensive: collapse any pre-existing duplicate cookie likes (same browser
        // counted twice across different IPs under the old constraint), keeping the
        // earliest row, so the new unique index can be created.
        DB::statement(<<<'SQL'
            DELETE t1 FROM entity_likes t1
            INNER JOIN entity_likes t2
              ON t1.entity_type = t2.entity_type
             AND t1.entity_id   = t2.entity_id
             AND t1.cookie_id   = t2.cookie_id
             AND t1.id > t2.id
        SQL);

        Schema::table('entity_likes', function (Blueprint $table) {
            $table->dropUnique(['entity_type', 'entity_id', 'ip_hash']);
            // One like per (entity, browser) — ip_hash is kept as a column for
            // analytics/abuse signals but no longer gates the count.
            $table->unique(['entity_type', 'entity_id', 'cookie_id']);
        });
    }

    public function down(): void
    {
        Schema::table('entity_likes', function (Blueprint $table) {
            $table->dropUnique(['entity_type', 'entity_id', 'cookie_id']);
            $table->unique(['entity_type', 'entity_id', 'ip_hash']);
        });
    }
};
