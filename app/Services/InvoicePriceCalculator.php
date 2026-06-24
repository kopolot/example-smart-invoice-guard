<?php

namespace App\Services;

use App\Models\Invoice;

class InvoicePriceCalculator
{
    public function calculateTotal(array|Invoice $data): float
    {
        $amount = $data['amount'] ?? $data->amount;
        $tax_rate = $data['tax_rate'] ?? $data->tax_rate;
        return $amount * (1 + $tax_rate / 100);
    }
}
