<?php

namespace Tests\Feature\Quotations;

use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The portal's soft-delete path (the MCP connector deliberately has none). A
 * quotation with an order attached can't be deleted — it's blocked with a 409
 * naming the order, not a 500 — and deleted rows drop out of every list.
 */
class AdminQuotationDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        $token = User::factory()->founder()->create()->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_soft_deletes_a_quotation_without_an_order(): void
    {
        $q = Quotation::factory()->create(['status' => 'draft']);

        $this->deleteJson("/api/v1/admin/quotations/{$q->id}", [], $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('message', "Quotation {$q->reference_code} deleted.");

        $this->assertSoftDeleted('quotations', ['id' => $q->id]);
    }

    public function test_delete_is_blocked_with_409_when_an_order_is_attached(): void
    {
        $q = Quotation::factory()->create(['status' => 'accepted']);
        $order = Order::factory()->create(['quotation_id' => $q->id]);

        $res = $this->deleteJson("/api/v1/admin/quotations/{$q->id}", [], $this->adminHeaders())
            ->assertStatus(409);

        // Names the order (+ ids) so the UI can link to it — never a 500.
        $res->assertJsonPath('order_id', $order->id);
        $res->assertJsonPath('order_number', $order->order_number);
        $this->assertStringContainsString($order->order_number, $res->json('message'));
        $this->assertDatabaseHas('quotations', ['id' => $q->id, 'deleted_at' => null]);
    }

    public function test_delete_unlinks_a_linked_inquiry_and_referral(): void
    {
        // A sent quote anchored to an inquiry (marked 'quoted') and a referral.
        $q = Quotation::factory()->create(['status' => 'sent']);
        $inquiry = Inquiry::create([
            'name' => 'Lead', 'email' => 'lead@example.com', 'message' => 'Interested',
            'status' => 'quoted', 'quotation_id' => $q->id,
        ]);
        $referral = Referral::create([
            'referrer_name' => 'Ref', 'referrer_email' => 'ref@example.com',
            'business_name' => 'Biz', 'commission_tier_pct' => 10,
            'status' => 'qualified', 'quotation_id' => $q->id,
        ]);

        $res = $this->deleteJson("/api/v1/admin/quotations/{$q->id}", [], $this->adminHeaders())->assertOk();
        $res->assertJsonPath('unlinked_inquiries', 1);
        $res->assertJsonPath('untied_referrals', 1);

        $this->assertSoftDeleted('quotations', ['id' => $q->id]);
        // Nothing left pointing at the deleted quote: the inquiry falls back to
        // 'reviewing', the referral to a plain 'new' claim.
        $this->assertDatabaseHas('inquiries', ['id' => $inquiry->id, 'quotation_id' => null, 'status' => 'reviewing']);
        $this->assertDatabaseHas('referrals', ['id' => $referral->id, 'quotation_id' => null, 'status' => 'new']);
    }

    public function test_deleted_quotation_disappears_from_the_admin_list(): void
    {
        $keep = Quotation::factory()->create(['status' => 'draft']);
        $gone = Quotation::factory()->create(['status' => 'draft']);
        $gone->delete();

        $refs = collect(
            $this->getJson('/api/v1/admin/quotations?status=draft', $this->adminHeaders())->json('data')
        )->pluck('reference_code')->all();

        $this->assertContains($keep->reference_code, $refs);
        $this->assertNotContains($gone->reference_code, $refs);
    }
}
