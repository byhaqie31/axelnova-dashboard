<?php

namespace Tests\Feature\Mail;

use App\Models\EmailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Statuses only move forward by rank (spec §5.2), so late or repeated
 * delivery reports can never undo a bounce or a complaint.
 */
class EmailLogStatusTest extends TestCase
{
    use RefreshDatabase;

    private function log(string $status = 'sent'): EmailLog
    {
        return EmailLog::create([
            'type' => 'invoice.sent', 'audience' => 'client', 'to_email' => 'a@example.com',
            'status' => $status, 'sent_at' => now(), 'status_changed_at' => now(),
        ]);
    }

    public function test_a_later_status_advances(): void
    {
        $log = $this->log();

        $this->assertTrue($log->advanceTo('delivered'));
        $this->assertSame('delivered', $log->fresh()->status);
    }

    public function test_a_lower_ranked_report_never_undoes_a_bounce(): void
    {
        $log = $this->log('bounced');

        $this->assertFalse($log->advanceTo('delivered'));
        $this->assertFalse($log->advanceTo('delayed'));
        $this->assertSame('bounced', $log->fresh()->status);
    }

    public function test_a_complaint_after_delivery_advances(): void
    {
        $log = $this->log('delivered');

        $this->assertTrue($log->advanceTo('complained', 'Marked as spam'));
        $this->assertSame('complained', $log->fresh()->status);
        $this->assertSame('Marked as spam', $log->fresh()->status_detail);
    }

    public function test_the_same_status_twice_is_a_no_op(): void
    {
        $log = $this->log('delivered');

        $this->assertFalse($log->advanceTo('delivered'));
    }

    public function test_a_concurrent_lower_report_cannot_overwrite_a_higher_one(): void
    {
        // Two webhook requests loaded the row while it was still `sent`.
        $log = $this->log();
        $stale = EmailLog::find($log->id);

        $this->assertTrue($log->advanceTo('delivered'));
        $this->assertFalse($stale->advanceTo('delayed', 'Delivery delayed'));

        $this->assertSame('delivered', $log->fresh()->status);
        $this->assertSame('delivered', $stale->status, 'the stale copy is refreshed to the stored status');
    }

    public function test_recovering_from_a_delay_clears_the_delay_note(): void
    {
        $log = $this->log();
        $log->advanceTo('delayed', 'Delivery delayed — Resend is still retrying.');

        $log->advanceTo('delivered');

        $this->assertNull($log->fresh()->status_detail);
    }

    public function test_a_problem_keeps_its_reason(): void
    {
        $log = $this->log();
        $log->advanceTo('bounced', 'mailbox does not exist');
        $log->advanceTo('complained');

        $this->assertSame('mailbox does not exist', $log->fresh()->status_detail);
    }

    public function test_problems_are_bounced_complained_or_failed(): void
    {
        foreach (['bounced' => true, 'complained' => true, 'failed' => true, 'delivered' => false, 'sent' => false, 'delayed' => false] as $status => $problem) {
            $this->assertSame($problem, $this->log($status)->isProblem(), $status);
        }
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->log()->advanceTo('opened');
    }
}
