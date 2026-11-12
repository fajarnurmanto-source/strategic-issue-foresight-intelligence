<?php

use App\Http\Controllers\Settings\AiModelController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Sifi\AiIssueExplanationController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings/ai-models', [AiModelController::class, 'edit'])->name('ai-models.edit');
    Route::post('settings/ai-models/disable', [AiModelController::class, 'disable'])->middleware('throttle:10,1')->name('ai-models.disable');
});

Route::middleware(['auth', 'verified', 'throttle:10,1'])->group(function () {
    Route::post('settings/ai-models/providers', [AiModelController::class, 'save']);
    Route::post('settings/ai-models/probe', [AiModelController::class, 'probe']);
    Route::post('settings/ai-models/assign', [AiModelController::class, 'assign']);
    Route::post('settings/ai-models/revoke', [AiModelController::class, 'revoke']);
    Route::get('sifi/issue-candidates/{candidate}/ai-explanation', [AiIssueExplanationController::class, 'show']);
    Route::post('sifi/issue-candidates/{candidate}/ai-explanation', [AiIssueExplanationController::class, 'run']);
});
