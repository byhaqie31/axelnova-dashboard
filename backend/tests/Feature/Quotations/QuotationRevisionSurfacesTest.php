<?php

namespace Tests\Feature\Quotations;

use App\Models\PricingConfig;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * §5 surfaces: the PDF data carries the revision label + reason (worded per
 * locale by the renderer), the order shows its revision chain, and every list
 * and report counts a quotation FAMILY once (acceptance tests 5 — data half —
 * and 7).
 */
class QuotationRevisionSurfacesTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-11 09:00:00');
        PricingConfig::factory()->create([
            'config' => ['currency' => 'MYR', 'valid_for_days' => 30, 'rush_multiplier' => 1.20,
                'base_packages' => [], 'modifiers' => [], 'addons' => []],
        ]);
        $this->founder = User::factory()->founder()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->founder->createToken('admin-spa', ['cockpit'])->plainTextToken];
    }

    private function document(float $total, array $extra = []): array
    {
        return array_merge([
            'layout' => 'detailed',
            'payload' => ['project' => 'Sistem Bengkel', 'sections' => [[
                'title' => 'Scope', 'rows' => [['title' => 'All', 'price' => $total]], 'total' => $total,
            ]]],
        ], $extra);
    }

    /** An accepted R1 (accepted 10 Oct) revised into R2 (11 Oct). Returns the family. */
    private function revisedFamily(bool $acceptR2 = false, string $locale = 'bm'): Quotation
    {
        Carbon::setTestNow('2026-10-10 09:00:00');
        $id = $this->postJson('/api/v1/admin/quotations', [
            'name' => 'M Automobile', 'email' => 'm-auto@example.com', 'locale' => $locale,
            'document' => $this->document(14340),
        ], $this->headers())->assertCreated()->json('data.id');
        $this->postJson("/api/v1/admin/quotations/{$id}/accept", [], $this->headers())->assertOk();

        Carbon::setTestNow('2026-10-11 09:00:00');
        $this->postJson("/api/v1/admin/quotations/{$id}/revisions", ['change_reason' => 'Tukar ke pelan 40/40/20'], $this->headers())->assertCreated();
        $this->putJson("/api/v1/admin/quotations/{$id}", [
            'name' => 'M Automobile', 'email' => 'm-auto@example.com',
            'document' => $this->document(10800, ['payment_plan' => 'lump_sum', 'phases' => [['label' => 'Terima', 'pct' => 40], ['label' => 'Demo', 'pct' => 40], ['label' => 'Go-live', 'pct' => 20]]]),
        ], $this->headers())->assertOk();
        if ($acceptR2) {
            $this->postJson("/api/v1/admin/quotations/{$id}/accept", [], $this->headers())->assertOk();
        }

        return Quotation::findOrFail($id);
    }

    public function test_the_r2_pdf_carries_the_revision_block_and_r1_prints_exactly_as_before(): void
    {
        $family = $this->revisedFamily();
        [$r1, $r2] = $family->revisions()->get()->all();

        $this->getJson("/api/v1/documents/{$r2->public_token}")
            ->assertOk()
            ->assertJsonPath('number', $family->reference_code)
            ->assertJsonPath('locale', 'bm')
            ->assertJsonPath('revision', [
                'number' => 2, 'replaces' => 1, 'replacedEvent' => 'accepted',
                'replacedDate' => '2026-10-10', 'reason' => 'Tukar ke pelan 40/40/20',
            ])
            ->assertJsonPath('paymentPlan.phases.0', ['n' => 1, 'label' => 'Terima', 'pct' => 40, 'amount' => 4320]);

        $this->getJson("/api/v1/documents/{$r1->public_token}")
            ->assertOk()
            ->assertJsonMissingPath('revision');
    }

    public function test_a_standard_layout_revision_carries_it_too(): void
    {
        $quotation = Quotation::factory()->create([
            'document' => ['layout' => 'standard', 'items' => [['title' => 'Site', 'qty' => 1, 'rate' => 5000]]],
        ]);
        $this->postJson("/api/v1/admin/quotations/{$quotation->id}/send", ['email' => false], $this->headers())->assertOk();
        $this->postJson("/api/v1/admin/quotations/{$quotation->id}/revisions", ['change_reason' => 'Typo in the scope'], $this->headers())->assertCreated();

        $r2 = $quotation->revisions()->where('number', 2)->firstOrFail();
        $this->getJson("/api/v1/documents/{$r2->public_token}")
            ->assertOk()
            ->assertJsonPath('layout', 'standard')
            ->assertJsonPath('revision.replacedEvent', 'sent')
            ->assertJsonPath('revision.reason', 'Typo in the scope');
    }

    public function test_the_builder_preview_of_a_revision_shows_its_label(): void
    {
        $family = $this->revisedFamily();

        $this->postJson('/api/v1/admin/quotations/preview', [
            'name' => 'M Automobile', 'quotation_id' => $family->id, 'revision' => 2,
            'document' => $this->document(10800),
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('number', $family->reference_code)
            ->assertJsonPath('revision.number', 2);
    }

    public function test_the_order_page_gets_the_revision_chain_flags_and_care_plans(): void
    {
        $family = $this->revisedFamily(acceptR2: true);
        $order = $family->order;

        $this->getJson("/api/v1/admin/orders/{$order->id}", $this->headers())
            ->assertOk()
            ->assertJsonPath('data.revision_number', 2)
            ->assertJsonPath('data.display_reference', "{$family->reference_code} · R2")
            ->assertJsonPath('data.revisions.0.status', 'superseded')
            ->assertJsonPath('data.revisions.1.status', 'accepted')
            ->assertJsonPath('data.revisions.1.is_current', true)
            ->assertJsonPath('data.revisions.1.change_reason', 'Tukar ke pelan 40/40/20')
            ->assertJsonPath('data.revisions.1.estimate_myr', 10800)
            ->assertJsonPath('data.payment_plan.phased', true);

        $this->getJson('/api/v1/admin/orders', $this->headers())
            ->assertOk()->assertJsonPath('data.0.display_reference', "{$family->reference_code} · R2");
    }

    public function test_lists_show_one_row_per_family_and_filter_revised_after_acceptance(): void
    {
        $revised = $this->revisedFamily();          // accepted R1 + open R2 draft
        $accepted = $this->revisedFamily(acceptR2: true);
        $plain = Quotation::factory()->create();    // a lone draft

        // Default list: accepted families hide — unless a revision is in progress on one.
        $rows = collect($this->getJson('/api/v1/admin/quotations', $this->headers())->assertOk()->json('data'));
        $this->assertEqualsCanonicalizing([$revised->id, $plain->id], $rows->pluck('id')->all());
        $row = $rows->firstWhere('id', $revised->id);
        $this->assertSame($revised->reference_code, $row['display_reference'], 'the family still presents its accepted R1');
        $this->assertSame(['number' => 2, 'status' => 'draft'], $row['open_revision']);

        // The day-to-day "draft, sent" view finds the R2 draft on the accepted family too.
        $working = collect($this->getJson('/api/v1/admin/quotations?status=draft,sent', $this->headers())->json('data'));
        $this->assertEqualsCanonicalizing([$revised->id, $plain->id], $working->pluck('id')->all());

        $revisedRows = collect($this->getJson('/api/v1/admin/quotations?revised=1', $this->headers())->json('data'));
        $this->assertEqualsCanonicalizing([$revised->id, $accepted->id], $revisedRows->pluck('id')->all());
        $this->assertSame("{$accepted->reference_code} · R2", $revisedRows->firstWhere('id', $accepted->id)['display_reference']);
    }

    public function test_reports_count_each_family_once(): void
    {
        $this->revisedFamily(acceptR2: true);      // accepted, 2 revisions
        $sent = Quotation::factory()->create();
        $this->postJson("/api/v1/admin/quotations/{$sent->id}/send", ['email' => false], $this->headers());
        Quotation::factory()->create();             // draft
        Cache::forget('admin:dashboard:counts');

        $this->getJson('/api/v1/admin/dashboard/counts', $this->headers())
            ->assertOk()
            ->assertJsonPath('quotations_total', 3)
            ->assertJsonPath('quotations_accepted', 1)
            ->assertJsonPath('quotations_decided', 2)
            ->assertJsonPath('quotations_conversion_pct', 50)
            ->assertJsonPath('quotations_revised', 1)
            ->assertJsonPath('orders_total', 1);
    }
}
