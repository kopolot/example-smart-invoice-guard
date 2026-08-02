<?php

namespace App\Services;

use App\Enums\InvoiceDomainEvent;
use Illuminate\Support\Facades\Cache;

class InvoiceAuditLogger
{
    public function cacheKey(int $invoiceId, InvoiceDomainEvent $event): string
    {
        return 'invoice:audit:'.$invoiceId.':'.$event->value;
    }

    /**
     * @return array{invoice_id: int, event: string, occurred_at: string}
     */
    public function log(int $invoiceId, InvoiceDomainEvent $event): array
    {
        $entry = [
            'invoice_id' => $invoiceId,
            'event' => $event->value,
            'occurred_at' => now()->toIso8601String(),
        ];

        Cache::forever($this->cacheKey($invoiceId, $event), $entry);

        return $entry;
    }
}
