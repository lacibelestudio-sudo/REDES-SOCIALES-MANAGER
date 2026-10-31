<?php

declare(strict_types=1);

use App\Http\Controllers\SalesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('sales', [SalesController::class, 'index'])->name('sales.index');
    Route::post('sales/campaigns', [SalesController::class, 'storeCampaign'])->name('sales.campaigns.store');
    Route::put('sales/campaigns/{campaign}', [SalesController::class, 'updateCampaign'])->name('sales.campaigns.update');
    Route::delete('sales/campaigns/{campaign}', [SalesController::class, 'destroyCampaign'])->name('sales.campaigns.destroy');
    Route::post('sales/leads', [SalesController::class, 'storeLead'])->name('sales.leads.store');
    Route::put('sales/leads/{lead}', [SalesController::class, 'updateLead'])->name('sales.leads.update');
    Route::delete('sales/leads/{lead}', [SalesController::class, 'destroyLead'])->name('sales.leads.destroy');
});
