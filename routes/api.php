<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\InvoiceController;

Route::middleware(['throttle:invoice-api'])->prefix('api')->name('api.')->group(function () {
    Route::post('invoice/generate', [InvoiceController::class, 'generate'])->name('invoice.generate');
});
