<?php

declare(strict_types=1);

use App\Http\Controllers\AdPlanController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('ads', [AdPlanController::class, 'index'])->name('ads.index');
    Route::post('ads/plans', [AdPlanController::class, 'store'])->name('ads.plans.store');
    Route::put('ads/plans/{plan}', [AdPlanController::class, 'update'])->name('ads.plans.update');
    Route::delete('ads/plans/{plan}', [AdPlanController::class, 'destroy'])->name('ads.plans.destroy');
});
