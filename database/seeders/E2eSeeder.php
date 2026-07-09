<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class E2eSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'e2e@example.com'],
            [
                'name' => 'E2E Tester',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $user->invoices()->forceDelete();

        Invoice::factory()->create([
            'user_id' => $user->id,
            'number' => 'E2E-PAID-001',
            'status' => InvoiceStatus::PAID,
            'total_amount' => 120,
            'date' => '2026-05-25',
            'due_date' => '2026-06-08',
            'sent_at' => now()->subDays(14),
        ]);

        Invoice::factory()->create([
            'user_id' => $user->id,
            'number' => 'E2E-OPEN-001',
            'status' => InvoiceStatus::UNPAID,
            'total_amount' => 50,
            'date' => '2026-07-01',
            'due_date' => '2026-07-20',
            'sent_at' => now()->subDays(7),
        ]);

        Invoice::factory()->create([
            'user_id' => $user->id,
            'number' => 'E2E-OVERDUE-001',
            'status' => InvoiceStatus::OVERDUE,
            'total_amount' => 75,
            'date' => '2026-06-08',
            'due_date' => '2026-07-05',
            'sent_at' => now()->subDays(21),
            'overdue_reminded_at' => now()->subDays(2),
        ]);
    }
}
