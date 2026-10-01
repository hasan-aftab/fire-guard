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
        Route::patch('clients/{client}/restore', [ClientController::class, 'restore'])->name('clients.restore');
        Route::patch('clients/{client}/entered-in-error', [ClientController::class, 'markError'])->name('clients.mark-error');
        Route::patch('clients/{client}/restore-error', [ClientController::class, 'restoreError'])->name('clients.restore-error');
        Route::delete('clients/{client}/permanent', [ClientController::class, 'purge'])->name('clients.purge');
        Route::resource('sites', SiteController::class)->only(['store', 'update', 'destroy']);
        Route::patch('sites/{site}/restore', [SiteController::class, 'restore'])->name('sites.restore');
        Route::patch('sites/{site}/entered-in-error', [SiteController::class, 'markError'])->name('sites.mark-error');
        Route::patch('sites/{site}/restore-error', [SiteController::class, 'restoreError'])->name('sites.restore-error');
        Route::delete('sites/{site}/permanent', [SiteController::class, 'purge'])->name('sites.purge');
        Route::resource('assets', AssetController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::patch('assets/{asset}/restore', [AssetController::class, 'restore'])->name('assets.restore');
        Route::patch('assets/{asset}/entered-in-error', [AssetController::class, 'markError'])->name('assets.mark-error');
        Route::patch('assets/{asset}/restore-error', [AssetController::class, 'restoreError'])->name('assets.restore-error');
        Route::delete('assets/{asset}/permanent', [AssetController::class, 'purge'])->name('assets.purge');
        Route::resource('technicians', TechnicianController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::patch('technicians/{technician}/restore', [TechnicianController::class, 'restore'])->name('technicians.restore');
        Route::patch('technicians/{technician}/entered-in-error', [TechnicianController::class, 'markError'])->name('technicians.mark-error');
        Route::patch('technicians/{technician}/restore-error', [TechnicianController::class, 'restoreError'])->name('technicians.restore-error');
        Route::delete('technicians/{technician}/permanent', [TechnicianController::class, 'purge'])->name('technicians.purge');
        Route::resource('inspections', InspectionController::class)->only(['index', 'store', 'update']);
        Route::patch('inspections/{inspection}/entered-in-error', [InspectionController::class, 'markError'])->name('inspections.mark-error');
        Route::patch('inspections/{inspection}/restore-error', [InspectionController::class, 'restoreError'])->name('inspections.restore-error');
        Route::get('compliance', [CertificateController::class, 'index'])->name('certificates.index');
        Route::resource('certificates', CertificateController::class)->only(['store', 'update']);
        Route::patch('certificates/{certificate}/entered-in-error', [CertificateController::class, 'markError'])->name('certificates.mark-error');
        Route::patch('certificates/{certificate}/restore-error', [CertificateController::class, 'restoreError'])->name('certificates.restore-error');
    });

    Route::post('notifications/generate', [FireNotificationController::class, 'store'])->name('notifications.generate');
    Route::patch('notifications/read-all', [FireNotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('notifications/{notification}', [FireNotificationController::class, 'update'])->name('notifications.update');
});

require __DIR__.'/settings.php';
