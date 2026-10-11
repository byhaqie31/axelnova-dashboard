<?php

namespace Tests\Unit\Mail;

use App\Support\Mail\HtmlToText;
use PHPUnit\Framework\TestCase;

/**
 * Every email is sent multipart: the plain-text part is derived from the
 * rendered HTML, so it must read like the email — no CSS, no hidden
 * preheader, links spelled out next to their label.
 */
class HtmlToTextTest extends TestCase
{
    private function sample(): string
    {
        return '<html><head><style>a{color:red}</style></head><body>'
            .'<div style="display: none; max-height: 0; overflow: hidden;">Preheader line</div>'
            .'<!-- BLOCK: heading -->'
            .'<h1>Hello &amp; welcome</h1><p>Line one<br>Line two</p>'
            .'<table><tr><td>Label</td><td>Value</td></tr></table>'
            .'<a href="https://x.test/pdf">View PDF&nbsp;&rarr;</a>'
            .'<p><a href="mailto:hi@x.test">hi@x.test</a></p></body></html>';
    }

    public function test_drops_head_css_hidden_preheader_and_comments(): void
    {
        $text = HtmlToText::convert($this->sample());

        $this->assertStringNotContainsString('color:red', $text);
        $this->assertStringNotContainsString('Preheader line', $text);
        $this->assertStringNotContainsString('BLOCK', $text);
    }

    public function test_keeps_readable_structure(): void
    {
        $text = HtmlToText::convert($this->sample());

        $this->assertStringContainsString('Hello & welcome', $text);
        $this->assertStringContainsString("Line one\nLine two", $text);
        $this->assertStringContainsString('Label  Value', $text);
    }

    public function test_spells_out_button_links_but_not_mailto(): void
    {
        $text = HtmlToText::convert($this->sample());

        $this->assertStringContainsString('View PDF → https://x.test/pdf', $text);
        $this->assertStringContainsString('hi@x.test', $text);
        $this->assertStringNotContainsString('mailto:', $text);
    }
}
