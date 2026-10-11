<?php

namespace Tests\Feature\Mail;

use App\Jobs\NotifyAdminJob;
use App\Mail\PartnerPasscodeChangedMail;
use App\Mail\PartnerPasscodeMail;
use App\Mail\ReferralCommissionMail;
use App\Mail\TeamPasswordChangedMail;
use App\Models\Order;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Every email goes through the queue (spec §5.7) — its delivery shows on the
 * record instead of the request waiting on SMTP. Since Phase 2 no email holds
 * a credential, so the partner access email is queued too.
 */
class QueueConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_former_synchronous_emails_are_queued(): void
    {
        foreach ([ReferralCommissionMail::class, TeamPasswordChangedMail::class, PartnerPasscodeChangedMail::class, PartnerPasscodeMail::class] as $class) {
            $this->assertTrue(is_subclass_of($class, ShouldQueue::class), "{$class} should be queued");
        }
    }

    public function test_the_commission_email_is_queued_and_says_so(): void
    {
        Mail::fake();
        $order = Order::factory()->create(['final_amount_myr' => 20000]);
        $referral = Referral::create([
            'referrer_name' => 'Ref Erra', 'referrer_email' => 'ref@example.com', 'business_name' => 'Big Whale Sdn Bhd',
            'relationship_tier' => 'closed', 'commission_tier_pct' => 15, 'status' => 'converted', 'linked_order_id' => $order->id,
        ]);
        $token = User::factory()->founder()->create()->createToken('admin-spa', ['cockpit'])->plainTextToken;

        $this->postJson("/api/v1/admin/referrals/{$referral->id}/commission-email", [], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('message', 'Commission email queued — its delivery shows on the referral.');

        Mail::assertQueued(ReferralCommissionMail::class, fn ($m) => $m->hasTo('ref@example.com'));
        $this->assertNotNull($referral->fresh()->commission_email_sent_at);
    }

    public function test_the_new_lead_alert_waits_only_outside_production(): void
    {
        Queue::fake();

        NotifyAdminJob::dispatchForNewLead(1);
        Queue::assertPushed(NotifyAdminJob::class, fn (NotifyAdminJob $job) => $job->delay !== null);

        Queue::fake();
        $this->app['env'] = 'production';

        NotifyAdminJob::dispatchForNewLead(2);
        Queue::assertPushed(NotifyAdminJob::class, fn (NotifyAdminJob $job) => $job->delay === null);
    }
}
