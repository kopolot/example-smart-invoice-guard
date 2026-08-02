<?php

use App\Http\Controllers\Api\InvoiceController;
use App\Http\Middleware\EnsureRequestIsIdempotent;
use Illuminate\Support\Facades\Route;

// Laravel already prefixes this file with /api — do not add prefix('api') again.
Route::middleware(['throttle:invoice-api'])->name('api.')->group(function () {
    Route::post('invoice/generate', [InvoiceController::class, 'generate'])
        ->middleware(EnsureRequestIsIdempotent::class)
        ->name('invoice.generate');
});
