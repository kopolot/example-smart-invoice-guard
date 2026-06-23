<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
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
        $fake_status = fake()->randomElement(\App\Enums\InvoiceStatus::cases());
        $random_user = User::inRandomOrder()->first();
        return [
            'amount' => $fake_amount,
            'tax_rate' => $fake_tax_rate,
            'total_amount' => $fake_total_amount,
            'status' => $fake_status,
            'date' => fake()->date(),
            'user_id' => $random_user->id,
        ];
    }
}
