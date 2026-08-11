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

    public const TOKEN_POOL_PATH = 'k6/api-tokens.json';

    /** Enough buckets for ~20 req/s under the 60/min per-user invoice-api limit. */
    public const POOL_SIZE = 25;

    /**
     * Prepare a dedicated user, Sanctum token pool, and invoice corpus for k6 runs.
     */
    public function run(): void
    {
        $tokens = [];

        $primary = $this->seedPrimaryUser();
        $tokens[] = $this->issueToken($primary, 'k6');

        $this->purgePoolUsers();

        for ($i = 1; $i <= self::POOL_SIZE; $i++) {
            $user = User::query()->create([
                'name' => "K6 Pool {$i}",
                'email' => sprintf('k6-pool-%03d@example.com', $i),
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
            ]);

            $tokens[] = $this->issueToken($user, 'k6-pool');
        }

        $this->writeTokenFiles($tokens);
        $this->seedInvoiceCorpus($primary);

        $this->command?->info('K6 user ready: '.self::EMAIL.' / '.self::PASSWORD);
        $this->command?->info('Token pool size: '.count($tokens).' (1 primary + '.self::POOL_SIZE.' pool)');
        $this->command?->info('Sanctum token written to storage/app/private/'.self::TOKEN_PATH);
        $this->command?->info('Token pool written to storage/app/private/'.self::TOKEN_POOL_PATH);
        $this->command?->info('Token pool copied to tests/k6/data/api-tokens.json');
        $this->command?->line('export K6_API_TOKEN='.$tokens[0]['token']);
    }

    private function seedPrimaryUser(): User
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

        return $user;
    }

    private function purgePoolUsers(): void
    {
        User::query()
            ->where('email', 'like', 'k6-pool-%@example.com')
            ->each(function (User $user): void {
                $user->tokens()->delete();
                $user->invoices()->forceDelete();
                $user->forceDelete();
            });
    }

    /**
     * @return array{email: string, token: string}
     */
    private function issueToken(User $user, string $name): array
    {
        return [
            'email' => $user->email,
            'token' => $user->createToken($name)->plainTextToken,
        ];
    }

    /**
     * @param  list<array{email: string, token: string}>  $tokens
     */
    private function writeTokenFiles(array $tokens): void
    {
        Storage::disk('local')->put(self::TOKEN_PATH, $tokens[0]['token']);
        Storage::disk('local')->put(
            self::TOKEN_POOL_PATH,
            json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );

        $scriptPoolPath = base_path('tests/k6/data/api-tokens.json');
        $directory = dirname($scriptPoolPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $scriptPoolPath,
            json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );
    }

    private function seedInvoiceCorpus(User $user): void
    {
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
    }
}
