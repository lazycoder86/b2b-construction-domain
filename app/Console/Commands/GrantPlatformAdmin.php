<?php

namespace App\Console\Commands;

use App\Facades\Audit;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('admin:grant {email : Email address of the user} {--revoke : Remove platform admin access instead}')]
#[Description('Grant or revoke access to the internal admin area (/admin).')]
class GrantPlatformAdmin extends Command
{
    public function handle(): int
    {
        $user = User::query()->whereRaw('lower(email) = ?', [strtolower((string) $this->argument('email'))])->first();

        if ($user === null) {
            $this->components->error('No user with that email address.');

            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');
        $user->forceFill(['is_platform_admin' => $grant])->save();

        Audit::record($grant ? 'admin.access_granted' : 'admin.access_revoked', organization: false, subject: $user, metadata: ['via' => 'console']);

        $this->components->info($grant
            ? "{$user->email} is now a platform admin. They need 2FA or a passkey to open /admin."
            : "{$user->email} is no longer a platform admin.");

        return self::SUCCESS;
    }
}
