<?php

namespace Tests\Feature\Invoice;

use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoiceSearchTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceSearchService $search;

    protected function setUp(): void
    {
        parent::setUp();

        $this->search = app(InvoiceSearchService::class);

        if (! $this->search->enabled()) {
            $this->markTestSkipped('Elasticsearch is disabled for Invoice Search tests.');
        }

        if (! $this->search->ping()) {
            $this->markTestSkipped('Elasticsearch is not available for Invoice Search tests.');
        }

        $this->search->flushIndex();
        $this->search->ensureIndex();
    }

    protected function tearDown(): void
    {
        if (isset($this->search)) {
            try {
                $this->search->flushIndex();
            } catch (\Throwable) {
                // Ignore cleanup failures when Elasticsearch is unavailable.
            }
        }

        parent::tearDown();
    }

    public function test_indexing_an_invoice_makes_it_searchable(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'number' => 'INV-SEARCH-ALPHA-001',
        ]);

        $this->search->index($invoice);

        $results = $this->search->search($user->id, 'SEARCH-ALPHA');

        $this->assertSame(1, $results->total());
        $this->assertSame($invoice->id, $results->items()[0]->id);
    }

    public function test_prefix_of_a_numeric_invoice_number_is_searchable(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'number' => '1231231332112312',
        ]);

        $this->search->index($invoice);

        $results = $this->search->search($user->id, '1231231332112');

        $this->assertSame(1, $results->total());
        $this->assertSame($invoice->id, $results->items()[0]->id);
    }

    public function test_search_is_scoped_to_the_authenticated_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $owned = Invoice::factory()->create([
            'user_id' => $owner->id,
            'number' => 'INV-OWNER-SCOPE-77',
        ]);
        Invoice::factory()->create([
            'user_id' => $other->id,
            'number' => 'INV-OWNER-SCOPE-77-OTHER',
        ]);

        $this->search->index($owned);
        $this->search->reindex($other->id);

        $results = $this->search->search($owner->id, 'OWNER-SCOPE-77');

        $this->assertSame(1, $results->total());
        $this->assertSame($owned->id, $results->items()[0]->id);
    }

    public function test_index_page_returns_elasticsearch_matches_for_query(): void
    {
        $user = User::factory()->create();
        $match = Invoice::factory()->create([
            'user_id' => $user->id,
            'number' => 'INV-ES-MATCH-42',
        ]);
        Invoice::factory()->create([
            'user_id' => $user->id,
            'number' => 'INV-UNRELATED-99',
        ]);

        $this->search->reindex($user->id);

        $this->actingAs($user)
            ->get(route('invoices.index', ['q' => 'ES-MATCH']))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('invoices/Index')
                    ->where('searchQuery', 'ES-MATCH')
                    ->has('invoicesPagination.data', 1)
                    ->where('invoicesPagination.data.0.id', $match->id)
                    ->where('invoicesPagination.data.0.number', 'INV-ES-MATCH-42'),
            );
    }

    public function test_deleting_an_invoice_removes_it_from_elasticsearch(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'number' => 'INV-DELETE-ES-01',
        ]);

        $this->search->index($invoice);
        $this->assertSame(1, $this->search->search($user->id, 'DELETE-ES-01')->total());

        $this->actingAs($user)
            ->delete(route('invoices.delete', $invoice))
            ->assertRedirect(route('invoices.index'));

        $this->assertSame(0, $this->search->search($user->id, 'DELETE-ES-01')->total());
    }

    public function test_reindex_command_indexes_existing_invoices(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->create([
            'user_id' => $user->id,
            'number' => 'INV-REINDEX-CMD-1',
        ]);

        $this->artisan('invoices:reindex', ['--fresh' => true])
            ->assertSuccessful();

        $this->assertSame(1, $this->search->search($user->id, 'REINDEX-CMD')->total());
    }
}
