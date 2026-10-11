<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * TR Labs — the first studio partner, launched as the "Axel Nova × TRLabs"
 * collaboration. Seeders never run on deploy, so the row ships as a data
 * migration (insert-if-missing). There is no admin editor yet: change this
 * partner, or link a shared project, with a new migration
 * (CollaborationPartner::linkProject()). See docs/global/COLLABORATIONS.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('collaboration_partners')->where('slug', 'trlabs')->exists()) {
            return;
        }

        DB::table('collaboration_partners')->insert([
            'slug' => 'trlabs',
            'name' => 'TRLabs',
            'tagline' => 'Made to move you.',
            'summary' => 'A founder-led studio in Malaysia building websites, business systems, mobile apps and AI products.',
            'story' => implode("\n\n", [
                'TRLabs Technologies is a founder-led studio in Malaysia. Thaqif Rosdi leads every project directly, '
                .'from the first conversation through design, build and launch, bringing in collaborators when a '
                .'project needs them.',
                'The studio plans before it codes, keeps scope practical with honest trade-offs, and builds products '
                .'meant to keep working long after launch. Its live work includes PeroduaSmart, a lead generation '
                .'platform for automotive sales, and Kenangan Majlis, digital invitations, RSVPs and photo collection '
                .'for Malaysian weddings and events.',
            ]),
            'logo_url' => '/logos/trlabs-wordmark.svg',
            'symbol_url' => '/logos/trlabs-symbol.svg',
            'website_url' => 'https://trlabs.my',
            'founder_name' => 'Thaqif Rosdi',
            'founder_role' => 'Founder & lead developer',
            'location' => 'Malaysia',
            'services' => json_encode(['Websites', 'Business systems', 'Mobile apps', 'AI products']),
            'partner_since' => '2026-10-01',
            'active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('collaboration_partners')->where('slug', 'trlabs')->delete();
    }
};
