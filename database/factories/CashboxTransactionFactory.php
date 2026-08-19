<?php

namespace Database\Factories;

use App\Enums\CashboxTransactionKind;
use App\Enums\CashboxTransactionType;
use App\Models\CashboxTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<CashboxTransaction>
 */
class CashboxTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => CashboxTransactionType::In,
            'amount' => $this->faker->numberBetween(1000, 10_000_000),
            'source_type' => null,
            'source_id' => null,
            'kind' => CashboxTransactionKind::CustomerPayment,
            'description' => null,
            'occurred_at' => now()->toDateString(),
        ];
    }

    public function out(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CashboxTransactionType::Out,
        ]);
    }

    public function opening(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CashboxTransactionType::In,
            'kind' => CashboxTransactionKind::OpeningBalance,
            'source_type' => null,
            'source_id' => null,
        ]);
    }

    /**
     * Link this transaction to a polymorphic source.
     *
     * Named fromSource() rather than for() to avoid silently overriding
     * Laravel's built-in Factory::for() (belongs-to relationship helper),
     * which has an incompatible signature.
     */
    public function fromSource(Model $source, CashboxTransactionKind $kind): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => $source::class,
            'source_id' => $source->getKey(),
            'kind' => $kind,
        ]);
    }
}
