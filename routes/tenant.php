<?php

use App\Http\Controllers\Tenant\AuditLogController;
use App\Http\Controllers\Tenant\BillingController;
use App\Http\Controllers\Tenant\InvitationController;
use App\Http\Controllers\Tenant\LeaveOrganizationController;
use App\Http\Controllers\Tenant\MemberController;
use App\Http\Controllers\Tenant\OrganizationLifecycleController;
use App\Http\Controllers\Tenant\OrganizationSecurityController;
use App\Http\Controllers\Tenant\OrganizationSettingsController;
use App\Http\Controllers\Tenant\TransferOwnershipController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Every route here is prefixed with /o/{organization} and runs behind the
| `auth`, `verified`, `tenant` and `tenant.mfa` middleware (see bootstrap/app.php).
| Inside these routes the current organization is set and all models
| using BelongsToOrganization are scoped to it.
|
| Gate routes on plan features with ->middleware('feature:csv_export').
|
| Child bindings with a custom key ({member:public_id}) are automatically
| scoped to the organization's relation (members(), invitations()).
|
*/

Route::name('tenant.')->group(function () {
    Route::inertia('dashboard', 'tenant/dashboard')->name('dashboard');

    Route::get('settings', [OrganizationSettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('settings', [OrganizationSettingsController::class, 'update'])->name('settings.update');
    Route::patch('settings/security', [OrganizationSecurityController::class, 'update'])->name('settings.security.update');
    Route::delete('settings', [OrganizationLifecycleController::class, 'destroy'])
        ->middleware('throttle:6,1')
        ->name('organization.destroy');
    Route::post('settings/export', [OrganizationLifecycleController::class, 'export'])
        ->middleware('throttle:3,60')
        ->name('organization.export');
    Route::get('exports/{export}', [OrganizationLifecycleController::class, 'download'])
        ->middleware('signed')
        ->name('organization.export.download');

    Route::get('members', [MemberController::class, 'index'])->name('members.index');
    Route::patch('members/{member:public_id}', [MemberController::class, 'update'])->name('members.update');
    Route::delete('members/{member:public_id}', [MemberController::class, 'destroy'])->name('members.destroy');
    Route::post('members/{member:public_id}/transfer-ownership', TransferOwnershipController::class)
        ->middleware('throttle:6,1')
        ->name('members.transfer-ownership');
    Route::delete('membership', LeaveOrganizationController::class)->name('membership.destroy');

    Route::post('invitations', [InvitationController::class, 'store'])
        ->middleware('throttle:30,60')
        ->name('invitations.store');
    Route::post('invitations/{invitation:public_id}/resend', [InvitationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('invitations.resend');
    Route::delete('invitations/{invitation:public_id}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

    Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');

    Route::get('billing', [BillingController::class, 'show'])->name('billing.show');
    Route::post('billing/subscribe', [BillingController::class, 'subscribe'])
        ->middleware('throttle:10,1')
        ->name('billing.subscribe');
    Route::post('billing/portal', [BillingController::class, 'portal'])
        ->middleware('throttle:10,1')
        ->name('billing.portal');
});
