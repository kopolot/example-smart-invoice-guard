<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_invoice_create_page(): void
    {
        $response = $this->get(route('invoices.create'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_access_invoice_create_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('invoices.create'));

        $response->assertOk();
    }
}
