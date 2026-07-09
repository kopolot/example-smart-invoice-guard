<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Invoice::factory(70)->create([
            'sent_at' => now()->subDays(rand(1, 30)),
        ]);

        Invoice::factory(20)->create();

        Invoice::factory(10)->create([
            'status' => InvoiceStatus::OVERDUE,
            'sent_at' => now()->subDays(rand(10, 45)),
            'due_date' => now()->subDays(rand(2, 14))->toDateString(),
            'overdue_reminded_at' => now()->subDays(rand(1, 7)),
        ]);
    }
}
