<?php

use App\Http\Middleware\EnsureRequestIsIdempotent;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\InvoiceController;

Route::middleware(['throttle:invoice-api'])->prefix('api')->name('api.')->group(function () {
    Route::post('invoice/generate', [InvoiceController::class, 'generate'])->middleware(EnsureRequestIsIdempotent::class)->name('invoice.generate');
});
