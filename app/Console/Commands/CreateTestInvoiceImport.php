<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\User;

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
            $file_path = storage_path('app/public/' . $file_name);
            $count = (int) $this->argument('count');
            $this->info("Creating $count test invoices");


            $userIDs = User::select('id')->limit(100)->get()->pluck('id')->toArray();
            $invoices = Invoice::factory($count)->make(fn() => [
                'user_id' => fake()->randomElement($userIDs),
            ]);

            // open file for writing
            $file = fopen($file_path, 'w');
            if (!$file) {
                $this->error("Failed to open file for writing");
                return parent::FAILURE;
            }

            // write header
            fputcsv($file, ['invoice_number', 'user_id', 'amount', 'tax_rate', 'tax_number', 'status', 'date']);

            // write invoices
            foreach ($invoices as $invoice) {
                fputcsv($file, [
                    $invoice->number,
                    $invoice->user_id,
                    $invoice->amount,
                    $invoice->tax_rate,
                    $invoice->tax_number,
                    $invoice->status->value,
                    $invoice->date,
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
