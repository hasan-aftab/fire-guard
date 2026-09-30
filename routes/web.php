<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FireNotificationController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\TechnicianController;
use App\Http\Middleware\SyncFireNotificationsAfterWrite;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware(SyncFireNotificationsAfterWrite::class)->group(function (): void {
        Route::resource('clients', ClientController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('sites', SiteController::class)->only(['store', 'update', 'destroy']);
        Route::resource('assets', AssetController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('technicians', TechnicianController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('inspections', InspectionController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('compliance', [CertificateController::class, 'index'])->name('certificates.index');
        Route::resource('certificates', CertificateController::class)->only(['store', 'update', 'destroy']);
    });

    Route::post('notifications/generate', [FireNotificationController::class, 'store'])->name('notifications.generate');
    Route::patch('notifications/read-all', [FireNotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('notifications/{notification}', [FireNotificationController::class, 'update'])->name('notifications.update');
});

require __DIR__.'/settings.php';
