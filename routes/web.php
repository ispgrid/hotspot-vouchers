<?php

use Illuminate\Support\Facades\Route;
use Ispgrid\HotspotVouchers\Http\CardsController;

Route::middleware(['web', 'auth'])->prefix('vouchers')->group(function () {
    Route::livewire('/', 'vouchers::batches')->name('vouchers.index');
    Route::livewire('batches/{batch}', 'vouchers::batch')->whereNumber('batch')->name('vouchers.batches.show');
    Route::get('batches/{batch}/print', [CardsController::class, 'print'])->whereNumber('batch')->name('vouchers.batches.print');
    Route::get('batches/{batch}/csv', [CardsController::class, 'csv'])->whereNumber('batch')->name('vouchers.batches.csv');
    Route::livewire('shops', 'vouchers::shops')->name('vouchers.shops.index');
    Route::livewire('shops/{shop}', 'vouchers::shop')->whereNumber('shop')->name('vouchers.shops.show');
});
