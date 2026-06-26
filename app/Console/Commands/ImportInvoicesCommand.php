<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use GuzzleHttp\Client;
use App\Models\Invoice;
use App\Services\InvoicePriceCalculator;
use Illuminate\Support\Facades\Crypt;

#[Signature('app:import-invoices {file} {batchSize=100}')]
#[Description('import invoices from csv file, you can generate a test file with `php artisan create-test-invoice-import {count}` and then use it with `php artisan import-invoices {url_to storage/app/public/test_invoices.csv}')]
class ImportInvoicesCommand extends Command
{
    protected int $batchSize;
    protected array $batch = [];

    public function __construct(protected InvoicePriceCalculator $invoicePriceCalculator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->batchSize = (int) $this->argument('batchSize');

        try {
            $filepath = $this->argument('file');

            if (!filter_var($filepath, FILTER_VALIDATE_URL)) {
                throw new \Exception('Invalid URL');
            }

            $this->info('Importing invoices from ' . $filepath);

            $client = new Client();
            $response = $client->get($filepath, ['stream' => true, 'verify' => false]);

            // open stream as resource
            $handle = $response->getBody()->detach();

            if (!is_resource($handle)) {
                throw new \Exception('Failed to open stream');
            }

            // skip header
            fgetcsv($handle, 0, ',');

            // parse line by line from stream
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                $this->processRow($row);
            }

            fclose($handle);

            // process remaining records that didn't fill the batch
            $this->processBatch();

            $this->info('Invoices imported successfully');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function processRow(array $row): void
    {
        [$number, $userId, $amount, $tax_rate, $tax_number, $status, $date] = $row;

        $invoiceData = [
            'number' => $number,
            'user_id' => $userId,
            'amount' => $amount,
            'tax_rate' => $tax_rate,
            'tax_number' => $tax_number,
            'status' => $status,
            'date' => $date,
        ];

        $invoiceData['total_amount'] = $this->invoicePriceCalculator->calculateTotal($invoiceData);

        $this->batch[] = $invoiceData;

        if (count($this->batch) >= $this->batchSize) {
            $this->processBatch();
        }
    }

    private function processBatch(): void
    {
        if (empty($this->batch)) {
            return;
        }

        try {
            $this->info('Processing batch of ' . count($this->batch) . ' invoices');

            $this->batch = array_map(function ($invoice) {
                $invoice['tax_number'] = Crypt::encryptString($invoice['tax_number']);
                return $invoice;
            }, $this->batch);

            $result = Invoice::upsert(
                $this->batch,
                ['number', 'user_id'],
                ['user_id', 'number', 'amount', 'tax_rate', 'tax_number', 'status', 'date', 'total_amount']
            );

            $this->info("Batch processed with $result invoices");

        } catch (\Exception $e) {
            $this->error("Error processing batch: {$e->getMessage()}");
        } finally {
            $this->batch = [];
        }
    }
}
