<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationDeletionController;
use App\Http\Controllers\StopImpersonationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Post-login landing (Fortify "home"): forwards to the user's organization.
    Route::get('dashboard', HomeController::class)->name('dashboard');

    Route::get('organizations/create', [OrganizationController::class, 'create'])->name('organizations.create');
    Route::post('organizations', [OrganizationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('organizations.store');

    // Organizations scheduled for deletion are outside the tenant routes (they are not accessible).
    Route::get('organizations/{organization}/deletion', [OrganizationDeletionController::class, 'show'])->name('organizations.deletion.show');
    Route::post('organizations/{organization}/restore', [OrganizationDeletionController::class, 'restore'])
        ->middleware('throttle:6,1')
        ->name('organizations.restore');
});

// Invitation links from email. Viewing works for guests; accepting needs a verified account.
Route::get('invitations/{token}', [InvitationAcceptanceController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('invitations.show');

Route::middleware(['auth', 'verified', 'throttle:10,1'])->group(function () {
    Route::post('invitations/{token}/accept', [InvitationAcceptanceController::class, 'accept'])->name('invitations.accept');
    Route::post('invitations/{token}/decline', [InvitationAcceptanceController::class, 'decline'])->name('invitations.decline');
});

Route::post('impersonation/stop', StopImpersonationController::class)
    ->middleware('auth')
    ->name('impersonation.stop');

require __DIR__.'/settings.php';

// Tenant routes (/o/{organization}/...) are registered in bootstrap/app.php from routes/tenant.php.
