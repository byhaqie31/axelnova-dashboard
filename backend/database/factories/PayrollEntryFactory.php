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
            'period_label' => fake()->unique()->numerify('2026-##'),
            'allowance_snapshot_myr' => $allowance,
            'task_extras_myr' => 0,
            'gross_myr' => $allowance,
            'paid_at' => null,
            'method' => null,
            'note' => null,
            'created_by' => User::factory()->founder(),
        ];
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
