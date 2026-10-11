<?php

namespace Tests\Feature\Quotations;

use App\Models\Order;
use App\Models\PricingConfig;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use App\Models\User;
use App\Services\Quoting\DocumentMapper;
use App\Services\Quoting\RevisionLocked;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * §1 of the revisions spec (docs/global/QUOTATION-REVISIONS.md): every quotation
 * is a family with numbered revisions, the family row mirrors its current
 * revision, and the migration gave every pre-existing quotation an R1 without
 * changing a single document a client already holds (acceptance test 1).
 */
class QuotationRevisionModelTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    private PricingConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = PricingConfig::factory()->create([
            'config' => ['currency' => 'MYR', 'valid_for_days' => 30, 'rush_multiplier' => 1.20,
                'base_packages' => [], 'modifiers' => [], 'addons' => []],
        ]);
        $this->founder = User::factory()->founder()->create();
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->founder->createToken('admin-spa', ['cockpit'])->plainTextToken];
    }

    /** A detailed (self-priced) admin document worth $total. */
    private function detailed(float $total, array $extras = []): array
    {
        return array_merge([
            'layout' => 'detailed',
            'payload' => ['project' => 'Sistem Bengkel', 'sections' => [[
                'title' => 'Scope', 'rows' => [['title' => 'All', 'price' => $total]], 'total' => $total,
            ]]],
        ], $extras);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_11_100001_create_quotation_revisions_table.php');
    }

    /** A pre-revision quotation row, written the way the old code left it (no R1). */
    private function legacyQuotation(array $overrides = []): int
    {
        return DB::table('quotations')->insertGetId(array_merge([
            'reference_code' => 'AXNQ-2001-'.Str::random(4),
            'source' => 'admin',
            'public_token' => Str::random(48),
            'name' => 'M Automobile',
            'email' => 'm-auto@example.com',
            'pricing_config_id' => $this->config->id,
            'form_payload' => json_encode(['packages' => []]),
            'document' => json_encode([
                'layout' => 'detailed',
                'deposit_amount_myr' => 2700,
                'payment_plan' => 'instalment',
                'instalment_months' => 12,
                'instalment_amount_myr' => 970,
                'payload' => ['project' => 'Sistem Bengkel', 'sections' => [[
                    'title' => 'Scope', 'rows' => [['title' => 'All', 'price' => 14340]], 'total' => 14340,
                ]]],
            ]),
            'locale' => 'bm',
            'estimate_min_myr' => 14340,
            'estimate_max_myr' => 14340,
            'estimate_eta_value' => 0,
            'estimate_eta_unit' => 'week',
            'status' => 'draft',
            'submitted_at' => now()->subDays(3),
            'issued_at' => now()->subDays(3),
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(2),
        ], $overrides));
    }

    public function test_backfill_gives_every_existing_quotation_exactly_one_r1_with_its_content(): void
    {
        $draftId = $this->legacyQuotation();
        $acceptedId = $this->legacyQuotation(['status' => 'accepted', 'sent_at' => now()->subDays(2)]);
        $deletedId = $this->legacyQuotation(['status' => 'expired', 'deleted_at' => now()]);
        $noTokenId = $this->legacyQuotation(['public_token' => null]);
        DB::table('quotation_addons')->insert([
            'quotation_id' => $acceptedId, 'addon_key' => 'seo', 'addon_label' => 'SEO setup', 'amount_myr' => 600,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $orderAt = now()->subDay()->startOfSecond();
        $order = Order::factory()->create(['quotation_id' => $acceptedId, 'created_at' => $orderAt]);
        $order->forceFill(['current_revision_id' => null])->saveQuietly();

        $this->migration()->backfill();

        foreach ([$draftId, $acceptedId, $deletedId, $noTokenId] as $id) {
            $row = DB::table('quotations')->find($id);
            $revisions = DB::table('quotation_revisions')->where('quotation_id', $id)->get();
            $this->assertCount(1, $revisions, "quotation {$id} has exactly one revision");
            $r1 = $revisions->first();
            $this->assertSame(1, (int) $r1->number);
            $this->assertSame((int) $r1->id, (int) $row->current_revision_id);
            $this->assertSame($row->status, $r1->status);
            $this->assertSame($row->public_token, $r1->public_token);
            $this->assertEquals(json_decode($row->document, true), json_decode($r1->document, true));
            $this->assertSame($row->locale, $r1->locale);
            $this->assertSame($row->estimate_max_myr, $r1->estimate_max_myr);
        }

        $accepted = QuotationRevision::where('quotation_id', $acceptedId)->first();
        $this->assertEquals([['addon_key' => 'seo', 'addon_label' => 'SEO setup', 'amount_myr' => 600]], $accepted->addons);
        $this->assertTrue($accepted->accepted_at->equalTo($orderAt));
        $this->assertSame($accepted->id, $order->fresh()->current_revision_id);

        // Idempotent: a second run adds nothing.
        $this->migration()->backfill();
        $this->assertSame(4, DB::table('quotation_revisions')->whereIn('quotation_id', [$draftId, $acceptedId, $deletedId, $noTokenId])->count());
    }

    public function test_existing_pdf_links_still_open_the_same_document(): void
    {
        $id = $this->legacyQuotation(['status' => 'sent', 'sent_at' => now()->subDay(), 'expires_at' => now()->addDays(20)]);
        $family = Quotation::findOrFail($id);
        $before = DocumentMapper::toDocumentData($family);

        $this->migration()->backfill();

        $this->getJson("/api/v1/documents/{$family->public_token}")
            ->assertOk()
            ->assertJsonPath('number', $family->reference_code)
            ->assertJsonPath('locale', 'bm')
            ->assertExactJson(json_decode(json_encode($before), true));
    }

    public function test_rollback_refuses_while_later_revisions_exist(): void
    {
        $quotation = Quotation::factory()->create();
        QuotationRevision::create(['quotation_id' => $quotation->id, 'number' => 2, 'status' => 'draft']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('1 quotation revision(s) beyond R1');

        $this->migration()->down();
    }

    public function test_every_creator_leaves_a_family_with_r1(): void
    {
        $admin = $this->postJson('/api/v1/admin/quotations', [
            'name' => 'Ali', 'email' => 'ali@example.com', 'document' => $this->detailed(5000),
        ], $this->headers())->assertCreated()->json('data.id');

        foreach ([Quotation::findOrFail($admin), Quotation::factory()->create()] as $quotation) {
            $this->assertSame(1, $quotation->revisions()->count());
            $r1 = $quotation->revisions()->first();
            $this->assertSame($r1->id, $quotation->fresh()->current_revision_id);
            $this->assertSame('draft', $r1->status);
            $this->assertSame($quotation->public_token, $r1->public_token);
        }
    }

    public function test_editing_a_draft_writes_r1_and_the_row_mirrors_it(): void
    {
        $id = $this->postJson('/api/v1/admin/quotations', [
            'name' => 'Ali', 'email' => 'ali@example.com', 'document' => $this->detailed(5000),
        ], $this->headers())->assertCreated()->json('data.id');

        $this->putJson("/api/v1/admin/quotations/{$id}", [
            'name' => 'Ali', 'email' => 'ali@example.com', 'locale' => 'bm', 'document' => $this->detailed(7000),
        ], $this->headers())->assertOk();

        $quotation = Quotation::findOrFail($id);
        $r1 = $quotation->revision();
        $this->assertSame(7000.0, $r1->asQuotation()->finalAmount());
        $this->assertSame('bm', $r1->locale);
        $this->assertEquals($r1->document, $quotation->document);
        $this->assertSame('bm', $quotation->locale);
        $this->assertSame(7000.0, $quotation->finalAmount());
    }

    public function test_send_and_lazy_expiry_land_on_the_revision_and_the_family_follows(): void
    {
        $quotation = Quotation::factory()->create(['public_token' => Str::random(48)]);

        $this->postJson("/api/v1/admin/quotations/{$quotation->id}/send", ['email' => false], $this->headers())->assertOk();

        $r1 = $quotation->revision()->refresh();
        $this->assertSame('sent', $r1->status);
        $this->assertNotNull($r1->sent_at);
        $this->assertSame('sent', $quotation->fresh()->status);

        $r1->forceFill(['expires_at' => now()->subDay()])->save();
        Quotation::expireOverdue();

        $this->assertSame('expired', $r1->fresh()->status);
        $this->assertSame('expired', $quotation->fresh()->status);
    }

    public function test_a_direct_row_write_lands_on_the_current_revision_and_frozen_content_refuses(): void
    {
        $quotation = Quotation::factory()->create();

        $quotation->update(['locale' => 'bm', 'status' => 'accepted']);

        $r1 = $quotation->revision()->refresh();
        $this->assertSame('bm', $r1->locale);
        $this->assertSame('accepted', $r1->status);
        $this->assertNotNull($r1->accepted_at);

        $this->expectException(RevisionLocked::class);
        $quotation->update(['estimate_max_myr' => 99999]);
    }

    public function test_a_revision_view_can_never_be_saved_over_the_family_row(): void
    {
        $quotation = Quotation::factory()->create();
        $view = $quotation->revision()->asQuotation();

        $this->expectException(\LogicException::class);
        $view->save();
    }
}
