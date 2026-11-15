<?php

namespace App\Support\Impersonation;

use App\Facades\Audit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Lets a platform admin act as a user for support, for a limited time.
 * Every start/stop is audited, and audit entries written meanwhile carry
 * the admin as `impersonator_id`. See App\Http\Middleware\HandleImpersonation.
 */
class Impersonation
{
    public const SESSION_IMPERSONATOR = 'impersonation.impersonator_id';

    public const SESSION_EXPIRES = 'impersonation.expires_at';

    /**
     * True while switching users, so the login listener doesn't log a normal login.
     */
    public static bool $switching = false;

    public function __construct(protected Session $session) {}

    public function active(): bool
    {
        return $this->session->has(self::SESSION_IMPERSONATOR);
    }

    public function impersonatorId(): ?int
    {
        $id = $this->session->get(self::SESSION_IMPERSONATOR);

        return is_int($id) ? $id : null;
    }

    public function impersonator(): ?User
    {
        $id = $this->impersonatorId();

        return $id !== null ? User::find($id) : null;
    }

    public function expiresAt(): ?CarbonImmutable
    {
        $expires = $this->session->get(self::SESSION_EXPIRES);

        return is_string($expires) ? CarbonImmutable::parse($expires) : null;
    }

    public function expired(): bool
    {
        return $this->expiresAt()?->isPast() ?? false;
    }

    public function start(User $admin, User $target, string $reason): void
    {
        if (! $admin->is_platform_admin || $target->is($admin) || $target->is_platform_admin || $this->active()) {
            throw new InvalidArgumentException('This user cannot be impersonated.');
        }

        Audit::record('admin.impersonation_started', subject: $target, metadata: ['reason' => $reason], actor: $admin);

        $this->switchTo($target);
        $this->session->put(self::SESSION_IMPERSONATOR, $admin->id);
        $this->session->put(self::SESSION_EXPIRES, now()->addMinutes((int) config('admin.impersonation_minutes'))->toIso8601String());
    }

    /**
     * End impersonation and log back in as the admin. Returns the admin, or null if they no longer exist.
     */
    public function stop(string $reason = 'stopped'): ?User
    {
        $admin = $this->impersonator();
        $target = Auth::user();

        if ($target instanceof User) {
            Audit::record('admin.impersonation_stopped', subject: $target, metadata: ['reason' => $reason], actor: $admin);
        }

        $this->session->forget([self::SESSION_IMPERSONATOR, self::SESSION_EXPIRES]);

        if ($admin === null || ! $admin->is_platform_admin) {
            Auth::guard('web')->logout();

            return null;
        }

        $this->switchTo($admin);

        return $admin;
    }

    protected function switchTo(User $user): void
    {
        static::$switching = true;

        try {
            Auth::guard('web')->login($user);
            $this->session->regenerate();
        } finally {
            static::$switching = false;
        }
    }
}
