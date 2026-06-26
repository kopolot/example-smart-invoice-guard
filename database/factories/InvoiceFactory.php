<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{

    protected static $userIDs = [];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fake_amount = fake()->randomFloat(2, 100, 1000);
        $fake_tax_rate = fake()->randomFloat(2, 0, 0.2);
        $fake_total_amount = $fake_amount * (1 + $fake_tax_rate);
        $fake_status = fake()->randomElement(InvoiceStatus::cases());
        $random_user_id = fake()->randomElement(static::$userIDs);

        return [
            'number' => fake()/*->unique()*/ ->numerify('INV-##########'),
            'amount' => $fake_amount,
            'tax_rate' => $fake_tax_rate,
            'total_amount' => $fake_total_amount,
            'tax_number' => fake()->numerify('##########'),
            'status' => $fake_status,
            'date' => fake()->date(),
            'user_id' => $random_user_id,
        ];
    }


    public function configure(): static
    {
        static::$userIDs = User::select('id')
            ->limit(100)
            ->inRandomOrder()
            ->get()
            ->pluck('id')
            ->toArray();
        return $this;
    }
}
