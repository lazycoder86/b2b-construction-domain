<?php

namespace App\Listeners;

use App\Facades\Audit;
use App\Models\User;
use App\Support\Impersonation\Impersonation;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;

/**
 * Platform-level audit entries for authentication and account security.
 * Registered by Laravel's event discovery (each typed handle* method).
 */
class AuditAuthenticationEvents
{
    public function handleLogin(Login $event): void
    {
        // Impersonation logs its own start/stop entries.
        if (Impersonation::$switching || ! $event->user instanceof User) {
            return;
        }

        Audit::record('auth.login', organization: false, actor: $event->user, metadata: ['remember' => $event->remember]);
    }

    public function handleFailed(Failed $event): void
    {
        // Credentials are never stored, only the attempted address.
        $email = is_string($event->credentials['email'] ?? null) ? $event->credentials['email'] : null;

        Audit::record(
            'auth.login_failed',
            organization: false,
            actor: $event->user instanceof User ? $event->user : null,
            metadata: ['email' => $email],
        );
    }

    public function handleLogout(Logout $event): void
    {
        if (Impersonation::$switching || ! $event->user instanceof User) {
            return;
        }

        Audit::record('auth.logout', organization: false, actor: $event->user);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            Audit::record('auth.password_reset', organization: false, actor: $event->user);
        }
    }

    public function handleTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        Audit::record('auth.two_factor_enabled', organization: false, actor: $event->user);
    }

    public function handleTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        Audit::record('auth.two_factor_disabled', organization: false, actor: $event->user);
    }

    public function handlePasskeyRegistered(PasskeyRegistered $event): void
    {
        if ($event->user instanceof User) {
            Audit::record('auth.passkey_registered', organization: false, actor: $event->user, metadata: ['name' => $event->passkey->name]);
        }
    }

    public function handlePasskeyDeleted(PasskeyDeleted $event): void
    {
        if ($event->user instanceof User) {
            Audit::record('auth.passkey_deleted', organization: false, actor: $event->user);
        }
    }
}
