<?php

use App\Http\Controllers\Sifi\Ih03jOwnerCollectionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('sifi/monitoring-objects/{object}/research-real-source')
    ->whereUuid('object')->group(function (): void {
        Route::get('/status', [Ih03jOwnerCollectionController::class, 'status'])
            ->middleware('throttle:20,1')->name('sifi.ih03j.status');
        Route::post('/', [Ih03jOwnerCollectionController::class, 'collect'])
            ->middleware('throttle:2,1')->name('sifi.ih03j.collect');
    });
