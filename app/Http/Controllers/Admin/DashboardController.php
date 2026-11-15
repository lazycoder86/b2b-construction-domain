<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/dashboard', [
            'stats' => [
                'organizations' => Organization::count(),
                'active_organizations' => Organization::where('status', OrganizationStatus::Active)->count(),
                'suspended_organizations' => Organization::where('status', OrganizationStatus::Suspended)->count(),
                'pending_deletion' => Organization::where('status', OrganizationStatus::PendingDeletion)->count(),
                'trialing' => Organization::where('trial_ends_at', '>', now())->count(),
                'paying' => DB::table('subscriptions')->whereIn('stripe_status', ['active', 'past_due', 'trialing'])->distinct()->count('organization_id'),
                'users' => User::count(),
                'new_users_7d' => User::where('created_at', '>=', now()->subDays(7))->count(),
            ],
            'recent' => AuditLog::query()
                ->with(['organization:id,name,public_id', 'impersonator:id,name'])
                ->latest('id')
                ->limit(15)
                ->get()
                ->map(fn (AuditLog $entry) => [...$entry->toDisplayArray(), 'organization' => $entry->organization?->name]),
        ]);
    }
}
