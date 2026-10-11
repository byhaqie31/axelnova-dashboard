<?php

namespace Tests\Feature\Collaborations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seeders never run on deploy (CI only runs `migrate`), so TR Labs ships as a
 * data migration. RefreshDatabase ran every migration, so the row is here.
 */
class TrlabsSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_trlabs_ships_live_with_its_logos(): void
    {
        $res = $this->getJson('/api/v1/collaborations/trlabs')->assertOk();

        $res->assertJsonPath('data.name', 'TRLabs')
            ->assertJsonPath('data.website_url', 'https://trlabs.my')
            ->assertJsonPath('data.founder_name', 'Thaqif Rosdi')
            ->assertJsonPath('data.logo_url', '/logos/trlabs-wordmark.svg')
            ->assertJsonPath('data.symbol_url', '/logos/trlabs-symbol.svg')
            ->assertJsonPath('data.projects', []);
    }

    public function test_the_logo_files_the_seed_points_at_exist(): void
    {
        $public = base_path('../frontend/public');

        if (! is_dir($public)) {
            $this->markTestSkipped('frontend/ is not mounted beside backend/ in this environment.');
        }

        $this->assertFileExists("{$public}/logos/trlabs-wordmark.svg");
        $this->assertFileExists("{$public}/logos/trlabs-symbol.svg");
        // A public/ folder named like a page route makes the static server
        // 301 /collaborations → /collaborations/ — keep partner assets out of it.
        $this->assertDirectoryDoesNotExist("{$public}/collaborations");
    }
}
