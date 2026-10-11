<?php

namespace Tests\Feature\Connector;

use App\Models\Invoice;
use App\Models\PricingConfig;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §6 — the MCP connector's revision contract (acceptance test 6):
 * create_revision → update_draft_quotation → (admin) accept → get_quotation
 * shows R2 current and R1 superseded. The connector opens and edits draft
 * revisions only; it never sends or accepts.
 */
class ConnectorRevisionTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();

        PricingConfig::factory()->create([
            'config' => ['currency' => 'MYR', 'valid_for_days' => 30, 'rush_multiplier' => 1.20,
                'base_packages' => [], 'modifiers' => [], 'addons' => []],
        ]);
        $this->founder = User::factory()->founder()->create();
    }

    // Each helper forgets the cached guard user: within one test the founder
    // switches between a connector token and a cockpit token, and Sanctum would
    // otherwise keep checking abilities against the first token it saw.
    private function connector(): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$this->founder->createToken('mcp-connector', ['connector:read', 'connector:draft'])->plainTextToken];
    }

    private function admin(): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$this->founder->createToken('admin-spa', ['cockpit'])->plainTextToken];
    }

    private function detailed(float $total, array $plan): array
    {
        return array_merge([
            'subtitle' => 'Sistem bengkel',
            'sections' => [['title' => 'Skop', 'rows' => [['title' => 'Semua', 'amount_myr' => $total]]]],
        ], $plan);
    }

    /** AXNQ-…: R1 (instalment, 14,340) drafted by the connector, then accepted in the admin. */
    private function acceptedViaConnector(): string
    {
        $ref = $this->postJson('/api/v1/connector/quotations/draft', [
            'client' => ['name' => 'M Automobile', 'email' => 'm-auto@example.com'],
            'project' => 'Sistem Bengkel — Pakej B',
            'detailed' => $this->detailed(14340, [
                'deposit_amount_myr' => 2700, 'payment_plan' => 'instalment', 'instalment_months' => 12,
                'instalment_amount_myr' => 970, 'first_instalment_date' => '2026-11-20', 'includes_care_plan' => true,
            ]),
        ], $this->connector())->assertCreated()->json('data.reference_code');

        $id = Quotation::where('reference_code', $ref)->value('id');
        $this->postJson("/api/v1/admin/quotations/{$id}/accept", [], $this->admin())->assertOk();

        return $ref;
    }

    private const R2_UPDATE = [
        'client' => ['name' => 'M Automobile', 'email' => 'm-auto@example.com'],
        'project' => 'Sistem Bengkel — Pakej B · 40/40/20',
        'detailed' => [
            'sections' => [['title' => 'Skop', 'rows' => [['title' => 'Semua', 'amount_myr' => 10800]]]],
            'payment_plan' => 'lump_sum',
            'phases' => [['label' => 'Semasa penerimaan', 'pct' => 40], ['label' => 'Semasa demo', 'pct' => 40], ['label' => 'Go-live', 'pct' => 20]],
            'care_plan_monthly_myr' => 220,
        ],
        'reseed_document' => true,
    ];

    public function test_create_revision_update_accept_get_quotation(): void
    {
        $ref = $this->acceptedViaConnector();

        // An accepted quote can't be edited until a revision is opened.
        $this->putJson("/api/v1/connector/quotations/{$ref}", self::R2_UPDATE, $this->connector())
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'create_revision'));

        // create_revision → R2 draft.
        $this->postJson("/api/v1/connector/quotations/{$ref}/revisions", ['change_reason' => 'Client switched to the 40/40/20 plan'], $this->connector())
            ->assertCreated()
            ->assertJsonPath('reference_code', $ref)
            ->assertJsonPath('revision_number', 2)
            ->assertJsonPath('data.revision.status', 'draft')
            ->assertJsonPath('data.revision.replaces', 1)
            ->assertJsonPath('data.family.status', 'accepted')
            ->assertJsonPath('data.family.current_revision_number', 1)
            ->assertJsonPath('data.family.draft_revision_number', 2);

        // update_draft_quotation edits the draft revision, not the accepted R1.
        $this->putJson("/api/v1/connector/quotations/{$ref}", self::R2_UPDATE, $this->connector())
            ->assertOk()
            ->assertJsonPath('data.revision.number', 2)
            ->assertJsonPath('data.estimate.max_myr', 10800)
            ->assertJsonPath('data.payment_plan.phases.0.amount', 4320)
            ->assertJsonPath('data.payment_plan.phases.2.amount', 2160)
            ->assertJsonPath('data.payment_plan.care_plan_monthly_myr', 220);
        $this->assertSame(14340.0, Quotation::where('reference_code', $ref)->first()->finalAmount(), 'R1 still stands');

        // Admin accepts R2 (the connector never accepts).
        $id = Quotation::where('reference_code', $ref)->value('id');
        $this->postJson("/api/v1/admin/quotations/{$id}/accept", [], $this->admin())->assertOk()->assertJsonPath('revision', 2);

        $this->getJson("/api/v1/connector/quotations/{$ref}", $this->connector())
            ->assertOk()
            ->assertJsonPath('data.family.status', 'accepted')
            ->assertJsonPath('data.family.current_revision_number', 2)
            ->assertJsonPath('data.family.open_revision_number', null)
            ->assertJsonPath('data.current_revision.number', 2)
            ->assertJsonPath('data.current_revision.status', 'accepted')
            ->assertJsonPath('data.current_revision.estimate', 10800)
            ->assertJsonPath('data.current_revision.change_reason', 'Client switched to the 40/40/20 plan')
            ->assertJsonPath('data.revisions.0.number', 1)
            ->assertJsonPath('data.revisions.0.status', 'superseded')
            ->assertJsonPath('data.revisions.0.estimate', 14340)
            ->assertJsonPath('data.revisions.1.status', 'accepted')
            ->assertJsonPath('data.revision.display_reference', "{$ref} · R2");

        // ?revision=1 reads the superseded terms back.
        $this->getJson("/api/v1/connector/quotations/{$ref}?revision=1", $this->connector())
            ->assertOk()->assertJsonPath('data.revision.status', 'superseded')->assertJsonPath('data.estimate.max_myr', 14340);

        // Nothing was paid under R1, so the netting has nothing to carry: phase 1
        // is simply due — no adjustment invoice.
        $this->assertSame(0, Invoice::where('type', 'adjustment')->count());
    }

    public function test_only_one_open_revision_and_a_reason_is_required(): void
    {
        $ref = $this->acceptedViaConnector();

        $this->postJson("/api/v1/connector/quotations/{$ref}/revisions", [], $this->connector())
            ->assertStatus(422)->assertJsonValidationErrors(['change_reason']);

        $this->postJson("/api/v1/connector/quotations/{$ref}/revisions", ['change_reason' => 'First change'], $this->connector())->assertCreated();
        $this->postJson("/api/v1/connector/quotations/{$ref}/revisions", ['change_reason' => 'Second change'], $this->connector())
            ->assertStatus(422)
            ->assertJsonPath('message', "R2 of {$ref} is still draft — edit, send or discard it before opening another revision.");
    }

    public function test_list_quotations_shows_the_revision_in_progress(): void
    {
        $ref = $this->acceptedViaConnector();
        $this->postJson("/api/v1/connector/quotations/{$ref}/revisions", ['change_reason' => 'Plan change'], $this->connector())->assertCreated();

        $row = collect($this->getJson('/api/v1/connector/quotations?status[]=draft', $this->connector())->assertOk()->json('data'))
            ->firstWhere('reference_code', $ref);
        $this->assertNotNull($row, 'status=draft finds the accepted family with a draft revision');
        $this->assertSame(1, $row['revision_number']);
        $this->assertSame(['number' => 2, 'status' => 'draft'], $row['open_revision']);
    }

    public function test_phases_must_split_a_lump_sum_into_100_percent(): void
    {
        $body = [
            'client' => ['name' => 'Ali', 'email' => 'ali@example.com'],
            'detailed' => $this->detailed(10000, ['phases' => [['pct' => 40], ['pct' => 40]]]),
        ];
        $this->postJson('/api/v1/connector/quotations/draft', $body, $this->connector())
            ->assertStatus(422)->assertJsonValidationErrors(['detailed.phases']);

        $body['detailed']['phases'][] = ['pct' => 20];
        $this->postJson('/api/v1/connector/quotations/draft', $body, $this->connector())
            ->assertCreated()
            ->assertJsonPath('data.payment_plan.phases.1.amount', 4000);
    }

    public function test_a_connector_token_cannot_reach_the_admin_accept(): void
    {
        $ref = $this->acceptedViaConnector();
        $this->postJson("/api/v1/connector/quotations/{$ref}/revisions", ['change_reason' => 'Plan change'], $this->connector())->assertCreated();

        $id = Quotation::where('reference_code', $ref)->value('id');
        $this->postJson("/api/v1/admin/quotations/{$id}/accept", [], $this->connector())->assertForbidden();
    }
}
