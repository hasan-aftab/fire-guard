<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificateDocumentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FireNotificationController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\InspectionPhotoController;
use App\Http\Controllers\ManagedUploadController;
use App\Http\Controllers\ReminderHistoryController;
use App\Http\Controllers\ReminderRecipientController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\TechnicianController;
use App\Http\Middleware\SyncFireNotificationsAfterWrite;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reminders', [ReminderHistoryController::class, 'index'])->name('reminders.index');
    Route::post('reminders/recipients', [ReminderRecipientController::class, 'store'])->name('reminders.recipients.store');
    Route::delete('reminders/recipients/{reminderRecipient}', [ReminderRecipientController::class, 'destroy'])->name('reminders.recipients.destroy');
    Route::get('reminders/templates/{type}/preview', [ReminderHistoryController::class, 'samplePreview'])
        ->whereIn('type', ['asset', 'certificate'])->name('reminders.templates.preview');
    Route::post('reminders/templates/{type}/test-email', [ReminderHistoryController::class, 'sampleTestEmail'])
        ->whereIn('type', ['asset', 'certificate'])->middleware('throttle:5,1')->name('reminders.templates.test-email');
    Route::get('reminders/{reminderDelivery}/preview', [ReminderHistoryController::class, 'preview'])->name('reminders.preview');
    Route::post('reminders/{reminderDelivery}/test-email', [ReminderHistoryController::class, 'testEmail'])
        ->middleware('throttle:5,1')->name('reminders.test-email');
    Route::post('reports', [ReportController::class, 'store'])->name('reports.store');
    Route::get('reports/pdf', [ReportController::class, 'legacyPdf'])->name('reports.pdf');
    Route::get('reports/{generatedReport}/view', [ReportController::class, 'show'])->name('reports.show');
    Route::get('reports/{generatedReport}/download', [ReportController::class, 'download'])->name('reports.download');
    Route::post('reports/{generatedReport}/regenerate', [ReportController::class, 'regenerate'])->name('reports.regenerate');

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
        Route::post('inspections/{inspection}/photos', [InspectionPhotoController::class, 'store'])->name('inspections.photos.store');
        Route::delete('inspections/{inspection}/photos/{photo}', [InspectionPhotoController::class, 'destroy'])->name('inspections.photos.destroy');
        Route::patch('inspections/{inspection}/entered-in-error', [InspectionController::class, 'markError'])->name('inspections.mark-error');
        Route::patch('inspections/{inspection}/restore-error', [InspectionController::class, 'restoreError'])->name('inspections.restore-error');
        Route::get('compliance', [CertificateController::class, 'index'])->name('certificates.index');
        Route::resource('certificates', CertificateController::class)->only(['store', 'update']);
        Route::post('certificates/{certificate}/document', [CertificateDocumentController::class, 'store'])->name('certificates.document.store');
        Route::delete('certificates/{certificate}/document/{document}', [CertificateDocumentController::class, 'destroy'])->name('certificates.document.destroy');
        Route::patch('certificates/{certificate}/entered-in-error', [CertificateController::class, 'markError'])->name('certificates.mark-error');
        Route::patch('certificates/{certificate}/restore-error', [CertificateController::class, 'restoreError'])->name('certificates.restore-error');
    });

    Route::get('inspections/{inspection}/photos/{photo}', [InspectionPhotoController::class, 'show'])->name('inspections.photos.show');
    Route::get('inspections/{inspection}/photos/{photo}/thumbnail', [InspectionPhotoController::class, 'thumbnail'])->name('inspections.photos.thumbnail');
    Route::get('inspections/{inspection}/photos/{photo}/download', [InspectionPhotoController::class, 'download'])->name('inspections.photos.download');
    Route::get('certificates/{certificate}/document/{document}', [CertificateDocumentController::class, 'show'])->name('certificates.document.show');
    Route::get('certificates/{certificate}/document/{document}/thumbnail', [CertificateDocumentController::class, 'thumbnail'])->name('certificates.document.thumbnail');
    Route::get('certificates/{certificate}/document/{document}/download', [CertificateDocumentController::class, 'download'])->name('certificates.document.download');
    Route::post('managed-uploads', [ManagedUploadController::class, 'store'])->name('managed-uploads.store');
    Route::put('managed-uploads/{upload}/chunks/{index}', [ManagedUploadController::class, 'chunk'])->name('managed-uploads.chunk');
    Route::post('managed-uploads/{upload}/complete', [ManagedUploadController::class, 'complete'])->name('managed-uploads.complete');
    Route::delete('managed-uploads/{upload}', [ManagedUploadController::class, 'destroy'])->name('managed-uploads.destroy');

    Route::post('notifications/generate', [FireNotificationController::class, 'store'])->name('notifications.generate');
    Route::patch('notifications/read-all', [FireNotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('notifications/{notification}', [FireNotificationController::class, 'update'])->name('notifications.update');
});

require __DIR__.'/settings.php';
