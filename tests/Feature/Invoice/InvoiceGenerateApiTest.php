<?php

namespace Tests\Feature\Invoice;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceGenerateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_generate_pdf_without_csrf_token(): void
    {
        Storage::fake('public');

        $response = $this->postJson('/api/invoice/generate', $this->payload(), [
            'X-Idempotency-Key' => (string) Str::uuid(),
        ]);

        $response->assertOk()->assertJsonStructure(['pdf_url']);
    }

    public function test_sanctum_token_can_generate_pdf_without_session(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/invoice/generate', $this->payload(), [
            'X-Idempotency-Key' => (string) Str::uuid(),
        ]);

        $response->assertOk()->assertJsonStructure(['pdf_url']);
    }

    public function test_missing_idempotency_key_is_rejected(): void
    {
        $response = $this->postJson('/api/invoice/generate', $this->payload());

        $response->assertUnprocessable();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'number' => 'API-TEST-001',
            'amount' => 100,
            'tax_rate' => 0.23,
            'tax_number' => '5250000000',
            'date' => '2026-08-01',
            'due_date' => '2026-08-15',
            'status' => 'unpaid',
        ];
    }
}
