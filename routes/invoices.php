<?php

use App\Http\Controllers\InvoiceController;
use App\Models\Invoice;
use Illuminate\Support\Facades\Route;

Route::prefix('invoices')->name('invoices.')->middleware(['auth'])->controller(InvoiceController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->can('create', Invoice::class)->name('create');
    Route::get('/{invoice:id}', 'show')->can('view', 'invoice')->name('show');
    Route::post('/store', 'store')->can('create', Invoice::class)->name('store');
    Route::get('/{invoice:id}/edit', 'edit')->can('update', 'invoice')->name('edit');
    Route::get('/{invoice:id}/delete', 'deleteMethod')->can('delete', 'invoice')->name('delete');
    Route::post('/{invoice:id}', 'update')->can('update', 'invoice')->name('update');
    Route::get('/{invoice:id}/pdf', 'pdf')->can('view', 'invoice')->name('pdf');
    Route::get('/{invoice:id}/pay', 'pay')->can('view', 'invoice')->name('pay');
});
