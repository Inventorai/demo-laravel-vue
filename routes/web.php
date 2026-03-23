<?php

use App\Http\Controllers\BroadcastingAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\SettingsController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
    Route::get('/properties/{id}', [PropertyController::class, 'show'])->name('properties.show');
    Route::get('/inspections', [InspectionController::class, 'index'])->name('inspections.index');
    Route::get('/inspections/{id}', [InspectionController::class, 'show'])->name('inspections.show');
    Route::patch('/inspections/{inspectionId}/areas/{areaId}', [InspectionController::class, 'updateArea'])->name('inspections.areas.update');
    Route::patch('/inspections/{inspectionId}/items/{itemId}', [InspectionController::class, 'updateItem'])->name('inspections.items.update');
    Route::post('/inspections/{inspectionId}/areas/{areaId}/photos', [InspectionController::class, 'uploadAreaPhoto'])->name('inspections.areas.uploadPhoto');
    Route::post('/inspections/{inspectionId}/items/{itemId}/photos', [InspectionController::class, 'uploadItemPhoto'])->name('inspections.items.uploadPhoto');
    Route::get('/api/phrases/search', [InspectionController::class, 'searchPhrases'])->name('api.phrases.search');
    Route::get('/api/activity', fn () => response()->json(\App\Services\ApiActivityTracker::recent(50)))->name('api.activity');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');

    Route::post('/broadcasting/auth', [BroadcastingAuthController::class, 'auth'])->name('broadcasting.auth');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
