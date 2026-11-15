import { Link, usePage } from '@inertiajs/react';
import billingRoutes from '@/routes/tenant/billing';

/**
 * Trial countdown / failed payment notice for people who can act on billing.
 */
export function BillingBanner() {
    const { currentOrganization } = usePage().props;

    if (
        !currentOrganization ||
        !(
            currentOrganization.permissions.includes('*') ||
            currentOrganization.permissions.includes('billing.view')
        )
    ) {
        return null;
    }

    const { billing, slug } = currentOrganization;
    const href = billingRoutes.show({ organization: slug });

    if (billing.status === 'past_due') {
        return (
            <div className="border-b border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                Your last payment failed.{' '}
                <Link href={href} className="font-medium underline">
                    Update your payment method
                </Link>{' '}
                to keep {billing.plan_name}.
            </div>
        );
    }

    if (billing.status === 'trialing' && billing.trial_days_left !== null) {
        return (
            <div className="border-b bg-muted/50 px-4 py-2 text-sm text-muted-foreground">
                {billing.trial_days_left === 1
                    ? '1 day'
                    : `${billing.trial_days_left} days`}{' '}
                left in your {billing.plan_name} trial.{' '}
                <Link
                    href={href}
                    className="font-medium text-foreground underline"
                >
                    Choose a plan
                </Link>
            </div>
        );
    }

    return null;
}
