<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class K6Seeder extends Seeder
{
    public const EMAIL = 'k6@example.com';

    public const PASSWORD = 'password';

    public const TOKEN_PATH = 'k6/api-token.txt';

    /**
     * Prepare a dedicated user, Sanctum token, and invoice corpus for k6 runs.
     */
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'K6 Load Tester',
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
            ],
        );

        $user->tokens()->delete();
        $plainTextToken = $user->createToken('k6')->plainTextToken;

        Storage::disk('local')->put(self::TOKEN_PATH, $plainTextToken);

        $user->invoices()->forceDelete();

        Invoice::factory()->count(80)->create([
            'user_id' => $user->id,
            'status' => InvoiceStatus::UNPAID,
        ]);

        Invoice::factory()->create([
            'user_id' => $user->id,
            'number' => 'K6-SEARCH-001',
            'status' => InvoiceStatus::PAID,
        ]);

        $this->command?->info('K6 user ready: '.self::EMAIL.' / '.self::PASSWORD);
        $this->command?->info('Sanctum token written to storage/app/private/'.self::TOKEN_PATH);
        $this->command?->line('export K6_API_TOKEN='.$plainTextToken);
    }
}
