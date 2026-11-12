<?php

use App\Http\Controllers\Sifi\ClaimComparisonPreviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:view,candidate'])->group(function () {
    Route::get('/sifi/issue-candidates/{candidate}/claim-comparison-preview', [ClaimComparisonPreviewController::class, 'show'])->whereUuid('candidate')->name('sifi.claim-comparison-preview.show');
    Route::post('/sifi/issue-candidates/{candidate}/claim-comparison-preview', [ClaimComparisonPreviewController::class, 'compare'])->whereUuid('candidate')->middleware('throttle:6,1')->name('sifi.claim-comparison-preview.compare');
});
