<?php

use Spark\Facades\Route;
use Spark\Pulse\Http\Controllers\ActionController;
use Spark\Pulse\Http\Controllers\DashboardController;
use Spark\Pulse\Http\Controllers\EntryController;
use Spark\Pulse\Http\Controllers\PerformanceController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Telemetry APIs
Route::get('/api/performance', [PerformanceController::class, 'index'])->name('api.performance');
Route::get('/api/entries', [EntryController::class, 'index'])->name('api.entries');
Route::get('/api/entries/{id}', [EntryController::class, 'show'])->name('api.show');
Route::get('/api/batch/{batchId}', [EntryController::class, 'batch'])->name('api.batch');

// Actions
Route::post('/api/clear', [ActionController::class, 'clear'])->name('api.clear');
Route::post('/api/prune', [ActionController::class, 'prune'])->name('api.prune');
Route::post('/api/toggle', [ActionController::class, 'toggle'])->name('api.toggle');
