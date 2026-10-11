<?php

namespace Tests\Feature\Quotations;

use App\Models\Quotation;
use App\Services\Quoting\QuotationRevisions;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The scheduler's first job (spec §5.8): an hourly sweep that expires sent
 * quotations past their validity, instead of waiting for someone to open the
 * admin list. The lazy on-read expiry stays as a safety net.
 */
class ExpireOverdueCommandTest extends TestCase
{
    use RefreshDatabase;

    private function sentQuotation(\DateTimeInterface $expiresAt): Quotation
    {
        $quotation = Quotation::factory()->create();
        QuotationRevisions::markSent($quotation->revision());
        $quotation->revision()->refresh()->forceFill(['expires_at' => $expiresAt])->save();

        return $quotation;
    }

    public function test_it_expires_only_overdue_sent_quotations(): void
    {
        $overdue = $this->sentQuotation(now()->subDay());
        $current = $this->sentQuotation(now()->addDays(10));

        $this->artisan('quotations:expire-overdue')->assertSuccessful();

        $this->assertSame('expired', $overdue->fresh()->status);
        $this->assertSame('sent', $current->fresh()->status);
    }

    public function test_it_is_scheduled_hourly_without_overlap(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'quotations:expire-overdue'));

        $this->assertNotNull($event, 'quotations:expire-overdue is not scheduled');
        $this->assertSame('0 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
