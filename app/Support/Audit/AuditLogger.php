<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\Impersonation\Impersonation;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLogger
{
    public function __construct(protected CurrentOrganization $current) {}

    /**
     * Record an action. Defaults: the organization is the current tenant and the
     * actor is the authenticated user. Runs inside the caller's transaction, so a
     * rolled-back action leaves no audit entry.
     *
     * @param  string  $action  dotted name, e.g. "member.role_changed" (see docs/audit-log.md)
     * @param  array<string, mixed>  $metadata  details such as old/new values; never secrets
     * @param  Organization|false|null  $organization  null = current tenant, false = platform-level
     */
    public function record(
        string $action,
        Organization|false|null $organization = null,
        ?Model $subject = null,
        array $metadata = [],
        ?User $actor = null,
        ?string $subjectLabel = null,
    ): AuditLog {
        $organization = $organization === false ? null : ($organization ?? $this->current->get());
        $actor ??= Auth::user() instanceof User ? Auth::user() : null;
        $request = $this->request();

        return AuditLog::create([
            'organization_id' => $organization?->id,
            'user_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'impersonator_id' => $this->impersonatorId($request, $actor),
            'action' => $action,
            'subject_type' => $subject !== null ? Str::snake(class_basename($subject)) : null,
            'subject_id' => $subject !== null ? (string) ($subject->getAttribute('public_id') ?? $subject->getKey()) : null,
            'subject_label' => $subjectLabel ?? ($subject !== null ? $this->labelFor($subject) : null),
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request !== null ? Str::limit((string) $request->userAgent(), 500, '') : null,
        ]);
    }

    protected function request(): ?Request
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return null;
        }

        return app()->bound('request') ? app('request') : null;
    }

    /**
     * Only attribute to an impersonator when the entry is about the impersonated session.
     */
    protected function impersonatorId(?Request $request, ?User $actor): ?int
    {
        if ($request === null || ! $request->hasSession()) {
            return null;
        }

        $impersonatorId = app(Impersonation::class)->impersonatorId();

        return $impersonatorId !== null && $actor?->id !== $impersonatorId ? $impersonatorId : null;
    }

    protected function labelFor(Model $subject): ?string
    {
        foreach (['name', 'email', 'slug'] as $attribute) {
            $value = $subject->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
