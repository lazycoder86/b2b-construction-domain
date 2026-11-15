<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Internal Admin Routes (/admin)
|--------------------------------------------------------------------------
|
| Platform staff only: `auth`, `verified`, `platform.admin` (is_platform_admin
| + MFA, not while impersonating). Pages also require a recent password
| confirmation. Every write is audited. Registered in bootstrap/app.php.
|
*/

Route::middleware('password.confirm')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::get('organizations/{organization:public_id}', [OrganizationController::class, 'show'])->withTrashed()->name('organizations.show');
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user:public_id}', [UserController::class, 'show'])->name('users.show');
    Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
});

Route::middleware('throttle:60,1')->group(function () {
    Route::patch('organizations/{organization:public_id}/status', [OrganizationController::class, 'updateStatus'])->name('organizations.status');
    Route::post('organizations/{organization:public_id}/trial', [OrganizationController::class, 'extendTrial'])->name('organizations.trial');
    Route::put('organizations/{organization:public_id}/entitlements', [OrganizationController::class, 'updateEntitlements'])->name('organizations.entitlements');
    Route::put('organizations/{organization:public_id}/flags/{flag}', [OrganizationController::class, 'updateFlag'])->name('organizations.flags');
    Route::post('users/{user:public_id}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');
});
