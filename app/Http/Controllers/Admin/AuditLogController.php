<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'actor' => ['nullable', 'string', 'max:255'],
        ]);

        $entries = AuditLog::query()
            ->with(['organization:id,name', 'impersonator:id,name'])
            ->when($filters['action'] ?? null, fn ($query, string $action) => $query->where('action', 'like', addcslashes($action, '%_').'%'))
            ->when($filters['actor'] ?? null, fn ($query, string $actor) => $query->where(fn ($query) => $query
                ->where('actor_email', 'ilike', '%'.addcslashes($actor, '%_').'%')
                ->orWhere('actor_name', 'ilike', '%'.addcslashes($actor, '%_').'%')))
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AuditLog $entry) => [...$entry->toDisplayArray(), 'organization' => $entry->organization?->name]);

        return Inertia::render('admin/audit-log', [
            'entries' => $entries,
            'filters' => ['action' => $filters['action'] ?? '', 'actor' => $filters['actor'] ?? ''],
        ]);
    }
}
