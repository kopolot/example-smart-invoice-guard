<?php

namespace App\Services;

use App\Enums\InvoiceDomainEvent;
use Illuminate\Support\Facades\Cache;

class InvoiceMetricsRecorder
{
    public function cacheKey(InvoiceDomainEvent $event): string
    {
        return 'invoice:metrics:'.$event->value;
    }

    public function record(InvoiceDomainEvent $event): int
    {
        $key = $this->cacheKey($event);

        if (! Cache::has($key)) {
            Cache::forever($key, 0);
        }

        return (int) Cache::increment($key);
    }
}
