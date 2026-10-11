<?php

namespace Tests\Feature\Mail;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Tests\Support\KitHtml;
use Tests\TestCase;

/**
 * Every block in the client layout, composed from our components with the
 * kit's placeholder text, must reproduce docs/global/email-kit/00-blocks.html.
 */
class KitBlocksFidelityTest extends TestCase
{
    public function test_every_block_matches_the_kit_catalogue(): void
    {
        Carbon::setTestNow('2026-10-11 08:46');
        View::addLocation(base_path('tests/Fixtures/views'));

        KitHtml::assertMatchesKit('00-blocks.html', view('kit-blocks')->render());
    }

    public function test_fixture_copies_match_the_docs_kit_when_reachable(): void
    {
        $docs = base_path('../docs/global/email-kit');
        if (! is_dir($docs)) {
            $this->markTestSkipped('docs/ is not mounted in this container');
        }

        foreach (glob($docs.'/*.html') as $file) {
            $this->assertFileEquals($file, base_path('tests/Fixtures/email-kit/'.basename($file)));
        }
    }
}
