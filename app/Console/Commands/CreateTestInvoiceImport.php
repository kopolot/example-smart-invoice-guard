<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:create-test-invoice-import {count=100}')]
#[Description('Command description')]
class CreateTestInvoiceImport extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $file_name = 'test_invoices.csv';
            $file_path = storage_path('app/public/'.$file_name);
            $count = (int) $this->argument('count');
            $this->info("Creating $count test invoices");

            $userIDs = User::select('id')->limit(100)->get()->pluck('id')->toArray();
            $invoices = Invoice::factory($count)->make(fn () => [
                'user_id' => fake()->randomElement($userIDs),
            ]);

            // open file for writing
            $file = fopen($file_path, 'w');
            if (! $file) {
                $this->error('Failed to open file for writing');

                return parent::FAILURE;
            }

            // write header
            fputcsv($file, ['invoice_number', 'user_id', 'amount', 'tax_rate', 'tax_number', 'status', 'date']);

            // write invoices
            foreach ($invoices as $invoice) {
                fputcsv($file, [
                    $invoice->number,
                    (string) $invoice->user_id,
                    (string) $invoice->amount,
                    (string) $invoice->tax_rate,
                    (string) $invoice->tax_number,
                    $invoice->status->value,
                    $invoice->date?->toDateString(),
                ]);
            }

            $this->info("Created $count test invoices in $file_path");

            return parent::SUCCESS;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return parent::FAILURE;
        }
    }
}
