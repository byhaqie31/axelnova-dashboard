<?php

namespace Database\Factories;

use App\Models\PayrollEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollEntry>
 */
class PayrollEntryFactory extends Factory
{
    public function definition(): array
    {
        $allowance = fake()->numberBetween(2000, 6000);

        return [
            'user_id' => User::factory()->engineer(),
            'kind' => PayrollEntry::KIND_MONTHLY,
            'period_label' => fake()->unique()->numerify('2026-##'),
            'one_time_type' => null,
            'allowance_snapshot_myr' => $allowance,
            'task_extras_myr' => 0,
            'discretionary_myr' => 0,
            'gross_myr' => $allowance,
            'paid_at' => null,
            'method' => null,
            'note' => null,
            'created_by' => User::factory()->founder(),
        ];
    }

    /** A one-off record — a discretionary bonus, no allowance snapshot. */
    public function oneTime(int $amount = 1000, string $type = 'signing'): static
    {
        return $this->state([
            'kind' => PayrollEntry::KIND_ONE_TIME,
            'one_time_type' => $type,
            'allowance_snapshot_myr' => null,
            'task_extras_myr' => 0,
            'discretionary_myr' => $amount,
            'gross_myr' => $amount,
        ]);
    }

    /** A pre-Task-7 row: hand-entered gross, no snapshot, no extras. */
    public function legacy(int $gross = 3500): static
    {
        return $this->state([
            'allowance_snapshot_myr' => null,
            'task_extras_myr' => 0,
            'gross_myr' => $gross,
        ]);
    }

    /** Already settled. */
    public function settled(): static
    {
        return $this->state([
            'paid_at' => now(),
            'method' => 'bank_transfer',
        ]);
    }
}
