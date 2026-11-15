<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request, Organization $organization): Response
    {
        Gate::authorize('viewAuditLog', $organization);

        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'actor' => ['nullable', 'string', 'max:255'],
        ]);

        $entries = $organization->auditLogs()
            ->with('impersonator:id,name')
            ->when($filters['action'] ?? null, fn ($query, string $action) => $query->where('action', 'like', addcslashes($action, '%_').'%'))
            ->when($filters['actor'] ?? null, fn ($query, string $actor) => $query->where(fn ($query) => $query
                ->where('actor_email', 'ilike', '%'.addcslashes($actor, '%_').'%')
                ->orWhere('actor_name', 'ilike', '%'.addcslashes($actor, '%_').'%')))
            ->latest('created_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AuditLog $entry) => $entry->toDisplayArray());

        return Inertia::render('tenant/audit-log', [
            'entries' => $entries,
            'filters' => [
                'action' => $filters['action'] ?? '',
                'actor' => $filters['actor'] ?? '',
            ],
            'actionGroups' => ['organization', 'member', 'invitation', 'ownership', 'billing'],
        ]);
    }
}
