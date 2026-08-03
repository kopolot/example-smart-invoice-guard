<?php

namespace App\Services;

use App\Models\Invoice;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class InvoiceSearchService
{
    private ?Client $client = null;

    private bool $indexReady = false;

    public function enabled(): bool
    {
        return (bool) config('elasticsearch.enabled');
    }

    public function ping(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        try {
            return $this->client()->ping()->asBool();
        } catch (\Throwable) {
            return false;
        }
    }

    public function indexName(): string
    {
        return (string) config('elasticsearch.invoices_index', 'invoices');
    }

    public function ensureIndex(): void
    {
        if ($this->indexReady || ! $this->enabled()) {
            return;
        }

        $client = $this->client();
        $index = $this->indexName();

        if (! $client->indices()->exists(['index' => $index])->asBool()) {
            $client->indices()->create([
                'index' => $index,
                'body' => [
                    'settings' => [
                        'number_of_shards' => 1,
                        'number_of_replicas' => 0,
                        'analysis' => [
                            'filter' => [
                                'invoice_edge_ngram' => [
                                    'type' => 'edge_ngram',
                                    'min_gram' => 5,
                                    'max_gram' => 40,
                                ],
                            ],
                            'analyzer' => [
                                'invoice_number' => [
                                    'type' => 'custom',
                                    'tokenizer' => 'keyword',
                                    'filter' => ['lowercase'],
                                ],
                                // Index prefixes of the full number (123… → 12, 123, 1231, …).
                                'invoice_number_prefix' => [
                                    'type' => 'custom',
                                    'tokenizer' => 'keyword',
                                    'filter' => ['lowercase', 'invoice_edge_ngram'],
                                ],
                                // Search with the raw query token — do not ngram the query itself.
                                'invoice_number_prefix_search' => [
                                    'type' => 'custom',
                                    'tokenizer' => 'keyword',
                                    'filter' => ['lowercase'],
                                ],
                            ],
                        ],
                    ],
                    'mappings' => [
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'user_id' => ['type' => 'integer'],
                            'number' => [
                                'type' => 'text',
                                'analyzer' => 'invoice_number',
                                'fields' => [
                                    'keyword' => ['type' => 'keyword'],
                                    'prefix' => [
                                        'type' => 'text',
                                        'analyzer' => 'invoice_number_prefix',
                                        'search_analyzer' => 'invoice_number_prefix_search',
                                    ],
                                    'tokens' => [
                                        'type' => 'text',
                                        'analyzer' => 'standard',
                                    ],
                                ],
                            ],
                            'status' => ['type' => 'keyword'],
                            'amount' => ['type' => 'float'],
                            'total_amount' => ['type' => 'float'],
                            'date' => ['type' => 'date', 'format' => 'yyyy-MM-dd||strict_date_optional_time'],
                            'due_date' => ['type' => 'date', 'format' => 'yyyy-MM-dd||strict_date_optional_time'],
                            'created_at' => ['type' => 'date'],
                            'updated_at' => ['type' => 'date'],
                        ],
                    ],
                ],
            ]);
        }

        $this->indexReady = true;
    }

    public function index(Invoice $invoice): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->ensureIndex();

        $this->client()->index([
            'index' => $this->indexName(),
            'id' => (string) $invoice->id,
            'refresh' => 'true',
            'body' => $this->document($invoice),
        ]);
    }

    public function forget(Invoice $invoice): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            $this->client()->delete([
                'index' => $this->indexName(),
                'id' => (string) $invoice->id,
                'refresh' => 'true',
            ]);
        } catch (ClientResponseException $exception) {
            if ($exception->getCode() !== 404) {
                throw $exception;
            }
        }
    }

    /**
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function search(int $userId, string $query, int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        $this->ensureIndex();

        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $from = ($page - 1) * $perPage;

        $response = $this->client()->search([
            'index' => $this->indexName(),
            'body' => [
                'from' => $from,
                'size' => $perPage,
                'track_total_hits' => true,
                'query' => [
                    'bool' => [
                        'filter' => [
                            ['term' => ['user_id' => $userId]],
                        ],
                        'should' => [
                            [
                                'multi_match' => [
                                    'query' => $query,
                                    'fields' => [
                                        'number^5',
                                        'number.tokens^2',
                                        'status',
                                    ],
                                    'fuzziness' => 'AUTO',
                                    'operator' => 'and',
                                ],
                            ],
                            [
                                'match' => [
                                    'number.prefix' => [
                                        'query' => $query,
                                        'boost' => 3,
                                    ],
                                ],
                            ],
                        ],
                        'minimum_should_match' => 1,
                    ],
                ],
                'sort' => [
                    ['_score' => 'desc'],
                    ['date' => 'desc'],
                ],
            ],
        ])->asArray();

        /** @var list<array{id?: int|string}> $hits */
        $hits = $response['hits']['hits'] ?? [];
        $total = (int) ($response['hits']['total']['value'] ?? 0);

        $ids = array_values(array_filter(array_map(
            static fn (array $hit): int => (int) ($hit['_id'] ?? $hit['_source']['id'] ?? 0),
            $hits,
        )));

        $invoices = $this->loadInvoicesPreservingOrder($ids, $userId);

        return new LengthAwarePaginator(
            $invoices,
            $total,
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
            ],
        );
    }

    /**
     * @return int Number of indexed invoices
     */
    public function reindex(?int $userId = null): int
    {
        $this->ensureIndex();

        $count = 0;

        Invoice::query()
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->orderBy('id')
            ->chunkById(100, function (Collection $invoices) use (&$count): void {
                foreach ($invoices as $invoice) {
                    $this->index($invoice);
                    $count++;
                }
            });

        return $count;
    }

    public function flushIndex(): void
    {
        if (! $this->enabled()) {
            return;
        }

        $index = $this->indexName();

        try {
            if ($this->client()->indices()->exists(['index' => $index])->asBool()) {
                $this->client()->indices()->delete(['index' => $index]);
            }
        } catch (ClientResponseException $exception) {
            if ($exception->getCode() !== 404) {
                throw $exception;
            }
        }

        $this->indexReady = false;
    }

    /**
     * @return array{
     *     id: int,
     *     user_id: int,
     *     number: string,
     *     status: string,
     *     amount: float,
     *     total_amount: float,
     *     date: ?string,
     *     due_date: ?string,
     *     created_at: ?string,
     *     updated_at: ?string
     * }
     */
    public function document(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'user_id' => (int) $invoice->user_id,
            'number' => (string) $invoice->number,
            'status' => $invoice->status->value,
            'amount' => (float) $invoice->amount,
            'total_amount' => (float) $invoice->total_amount,
            'date' => $invoice->date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'created_at' => $invoice->created_at?->toIso8601String(),
            'updated_at' => $invoice->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Invoice>
     */
    private function loadInvoicesPreservingOrder(array $ids, int $userId): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $invoices = Invoice::query()
            ->where('user_id', $userId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->map(fn (int $id) => $invoices->get($id))
            ->filter()
            ->values();
    }

    private function client(): Client
    {
        return $this->client ??= ClientBuilder::create()
            ->setHosts([(string) config('elasticsearch.host')])
            ->setRetries(1)
            ->build();
    }
}
