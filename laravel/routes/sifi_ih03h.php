<?php

use App\Http\Controllers\Sifi\Ih03hSnapshotController;
use Illuminate\Support\Facades\Route;

// Local-only public-metadata sample; no provider HTTP, no database mutations.
Route::middleware(['auth', 'verified', 'throttle:30,1'])
    ->get('/sifi/research-source-preview', [Ih03hSnapshotController::class, 'show'])
    ->name('sifi.ih03h.source-preview');
