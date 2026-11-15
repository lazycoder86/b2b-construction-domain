import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { stop } from '@/routes/impersonation';

/**
 * Always visible while a platform admin is acting as this user.
 */
export function ImpersonationBanner() {
    const { impersonation, auth } = usePage().props;

    if (!impersonation) {
        return null;
    }

    const expires = impersonation.expires_at
        ? new Date(impersonation.expires_at).toLocaleTimeString(undefined, {
              timeStyle: 'short',
          })
        : null;

    return (
        <div
            role="alert"
            className="flex flex-wrap items-center justify-between gap-2 bg-amber-400 px-4 py-2 text-sm text-amber-950"
        >
            <span>
                <strong>{impersonation.impersonator}</strong> is signed in as{' '}
                <strong>{auth.user.name}</strong>. Security, billing and
                destructive actions are disabled.
                {expires && ` Ends automatically at ${expires}.`}
            </span>
            <Button
                size="sm"
                variant="secondary"
                onClick={() => router.post(stop.url())}
            >
                Stop impersonating
            </Button>
        </div>
    );
}
