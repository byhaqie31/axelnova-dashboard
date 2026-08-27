<?php

namespace Tests\Feature\Dashboard;

use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard counts endpoint — the single cached request the admin landing
 * page reads its stat tiles from (replacing five full list fetches).
 */
class DashboardCountsTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        $token = User::factory()->founder()->create()->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_counts_reflect_the_seeded_rows(): void
    {
        Quotation::factory()->count(3)->create(['status' => 'sent']);
        Quotation::factory()->create(['status' => 'draft']);
        // Each order factory mints its own quotation (factory default: draft).
        Order::factory()->count(2)->create();
        Inquiry::create([
            'name' => 'Fresh Lead',
            'email' => 'fresh@example.com',
            'message' => 'Hello.',
            'source' => 'web',
            'status' => 'new',
        ]);

        $this->getJson('/api/v1/admin/dashboard/counts', $this->adminHeaders())
            ->assertOk()
            ->assertJson([
                // 3 sent + 1 draft + 2 minted by the order factories' quotations.
                'quotations_total' => 6,
                'quotations_draft' => 3,
                'orders_total' => 2,
                'inquiries_new' => 1,
                'referrals_active' => 0,
                'views_7d' => 0,
            ]);
    }

    public function test_counts_require_a_cockpit_token(): void
    {
        $this->getJson('/api/v1/admin/dashboard/counts')->assertUnauthorized();
    }
}
