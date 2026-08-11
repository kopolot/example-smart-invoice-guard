<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\K6Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class K6SeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_seeds_primary_user_token_and_pool(): void
    {
        $scriptPoolPath = base_path('tests/k6/data/api-tokens.json');
        $previous = file_exists($scriptPoolPath) ? file_get_contents($scriptPoolPath) : null;

        try {
            $this->seed(K6Seeder::class);

            $this->assertDatabaseHas('users', ['email' => K6Seeder::EMAIL]);
            $this->assertSame(
                1 + K6Seeder::POOL_SIZE,
                User::query()->where('email', 'like', 'k6%@example.com')->count(),
            );

            Storage::disk('local')->assertExists(K6Seeder::TOKEN_PATH);
            Storage::disk('local')->assertExists(K6Seeder::TOKEN_POOL_PATH);

            $pool = json_decode(Storage::disk('local')->get(K6Seeder::TOKEN_POOL_PATH), true);

            $this->assertIsArray($pool);
            $this->assertCount(1 + K6Seeder::POOL_SIZE, $pool);
            $this->assertSame(K6Seeder::EMAIL, $pool[0]['email']);
            $this->assertNotEmpty($pool[0]['token']);
            $this->assertSame(
                Storage::disk('local')->get(K6Seeder::TOKEN_PATH),
                $pool[0]['token'],
            );

            $this->assertFileExists($scriptPoolPath);
            $scriptPool = json_decode((string) file_get_contents($scriptPoolPath), true);
            $this->assertCount(1 + K6Seeder::POOL_SIZE, $scriptPool);
            $this->assertSame($pool[0]['token'], $scriptPool[0]['token']);
        } finally {
            if ($previous === null) {
                if (file_exists($scriptPoolPath)) {
                    unlink($scriptPoolPath);
                }
            } else {
                file_put_contents($scriptPoolPath, $previous);
            }
        }
    }

    public function test_reseed_replaces_pool_users_and_tokens(): void
    {
        $scriptPoolPath = base_path('tests/k6/data/api-tokens.json');
        $previous = file_exists($scriptPoolPath) ? file_get_contents($scriptPoolPath) : null;

        try {
            $this->seed(K6Seeder::class);
            $firstPool = json_decode(Storage::disk('local')->get(K6Seeder::TOKEN_POOL_PATH), true);

            $this->seed(K6Seeder::class);
            $secondPool = json_decode(Storage::disk('local')->get(K6Seeder::TOKEN_POOL_PATH), true);

            $this->assertSame(
                1 + K6Seeder::POOL_SIZE,
                User::query()->where('email', 'like', 'k6%@example.com')->count(),
            );
            $this->assertNotSame($firstPool[0]['token'], $secondPool[0]['token']);
            $this->assertNotSame($firstPool[1]['token'], $secondPool[1]['token']);
        } finally {
            if ($previous === null) {
                if (file_exists($scriptPoolPath)) {
                    unlink($scriptPoolPath);
                }
            } else {
                file_put_contents($scriptPoolPath, $previous);
            }
        }
    }
}
