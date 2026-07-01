<?php

use App\Http\Controllers\InvoiceController;
use App\Http\Middleware\EnsureRequestIsIdempotent;
use App\Models\Invoice;
use Illuminate\Support\Facades\Route;

Route::prefix('invoices')->name('invoices.')->controller(InvoiceController::class)->group(function () {
    Route::middleware(['auth'])->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->can('create', Invoice::class)->name('create');
        Route::get('/{invoice:id}', 'show')->can('view', 'invoice')->name('show');
        Route::post('/store', 'store')->middleware(EnsureRequestIsIdempotent::class)->can('create', Invoice::class)->name('store');
        Route::get('/{invoice:id}/edit', 'edit')->can('update', 'invoice')->name('edit');
        Route::delete('/{invoice:id}', 'deleteMethod')->can('delete', 'invoice')->name('delete');
        Route::put('/{invoice:id}', 'update')->can('update', 'invoice')->name('update');
        Route::get('/{invoice:id}/pdf', 'pdf')->can('view', 'invoice')->name('pdf');
        Route::patch('/{invoice:id}/send', 'send')->can('view', 'invoice')->name('send');
    });
    Route::patch('/{invoice:id}/pay', 'pay')->name('pay');
    Route::get('/{invoice:id}/pay', 'payForm')->name('pay.form');
});
