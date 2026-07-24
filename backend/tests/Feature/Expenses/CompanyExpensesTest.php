<?php

namespace Tests\Feature\Expenses;

use App\Models\CompanyExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The company-spending ledger (formerly "marketing expenses" — the tracker was
 * always general company spend, so table/model/routes were renamed to match).
 * Cockpit-only: founder reads and writes at /v1/admin/expenses; the old
 * /v1/admin/marketing-expenses path is gone.
 */
class CompanyExpensesTest extends TestCase
{
    use RefreshDatabase;

    private function cockpitToken(User $founder): array
    {
        $token = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_the_founder_can_list_company_expenses(): void
    {
        $founder = User::factory()->create(['role' => 'founder']);
        CompanyExpense::create([
            'entered_by' => $founder->id,
            'category' => 'Software',
            'amount_myr' => 120.50,
            'spent_at' => now()->toDateString(),
            'note' => 'Figma seat',
        ]);

        $this->getJson('/api/v1/admin/expenses', $this->cockpitToken($founder))
            ->assertOk()
            ->assertJsonPath('data.0.category', 'Software')
            ->assertJsonStructure(['data', 'meta', 'totals' => ['amount_myr']]);
    }

    public function test_the_founder_can_record_an_expense_into_the_renamed_table(): void
    {
        $founder = User::factory()->create(['role' => 'founder']);

        $this->postJson('/api/v1/admin/expenses', [
            'category' => 'Hosting',
            'amount_myr' => 45,
            'spent_at' => now()->toDateString(),
        ], $this->cockpitToken($founder))
            ->assertCreated()
            ->assertJsonPath('data.category', 'Hosting');

        $this->assertDatabaseHas('company_expenses', [
            'category' => 'Hosting',
            'entered_by' => $founder->id,
        ]);
    }

    public function test_the_old_marketing_expenses_path_is_gone(): void
    {
        $founder = User::factory()->create(['role' => 'founder']);

        $this->getJson('/api/v1/admin/marketing-expenses', $this->cockpitToken($founder))
            ->assertNotFound();
    }
}
