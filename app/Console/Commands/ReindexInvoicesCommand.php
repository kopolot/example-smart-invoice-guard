<?php

namespace App\Console\Commands;

use App\Services\InvoiceSearchService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('invoices:reindex {--user= : Limit reindex to a single user id} {--fresh : Delete and recreate the invoices index first}')]
#[Description('Index invoices into Elasticsearch for full-text search')]
class ReindexInvoicesCommand extends Command
{
    public function __construct(private InvoiceSearchService $invoiceSearchService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->invoiceSearchService->enabled()) {
            $this->components->error('Elasticsearch is disabled (ELASTICSEARCH_ENABLED=false).');

            return self::FAILURE;
        }

        if (! $this->invoiceSearchService->ping()) {
            $this->components->error('Elasticsearch is not reachable at '.config('elasticsearch.host'));

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->invoiceSearchService->flushIndex();
            $this->components->info('Dropped Elasticsearch index ['.$this->invoiceSearchService->indexName().'].');
        }

        $userId = $this->option('user') !== null ? (int) $this->option('user') : null;
        $count = $this->invoiceSearchService->reindex($userId);

        $this->components->info("Indexed {$count} invoice(s) into [{$this->invoiceSearchService->indexName()}].");

        return self::SUCCESS;
    }
}
