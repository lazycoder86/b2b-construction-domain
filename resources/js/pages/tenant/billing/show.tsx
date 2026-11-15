import { Deferred, Head, router } from '@inertiajs/react';
import { Check, CreditCard } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useCurrentOrganization } from '@/hooks/use-current-organization';
import { organizationSlug } from '@/lib/tenant';
import { cn } from '@/lib/utils';
import billingRoutes from '@/routes/tenant/billing';
import type { BillingStatus, SharedPageProps } from '@/types';

type Interval = 'monthly' | 'yearly';

type PlanOption = {
    key: string;
    name: string;
    description: string;
    per_seat: boolean;
    features: string[];
    limits: Record<string, number | null>;
    prices: {
        interval: Interval;
        amount: number;
        formatted: string;
        available: boolean;
    }[];
};

type InvoiceRow = {
    id: string;
    date: string;
    total: string;
    status: string;
    url: string | null;
};

type Props = {
    billing: {
        plan: string;
        plan_name: string;
        status: BillingStatus;
        trial_ends_at: string | null;
        trial_days_left: number | null;
        interval: Interval | null;
        ends_at: string | null;
        payment_method: { brand: string; last_four: string | null } | null;
    };
    plans: PlanOption[];
    seats: { used: number; limit: number | null };
    invoices?: InvoiceRow[];
    stripeEnabled: boolean;
    hasSubscription: boolean;
    isCustomer: boolean;
    canManage: boolean;
    checkout: 'success' | 'cancelled' | null;
    errors?: { plan?: string };
};

// Human labels for feature keys in config/billing.php. Unknown keys are humanized.
const FEATURE_LABELS: Record<string, string> = {
    csv_export: 'CSV export',
    csv_import: 'CSV import',
    api_access: 'API access',
    webhooks: 'Webhooks',
};

const featureLabel = (key: string) =>
    FEATURE_LABELS[key] ??
    key.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());

const formatDate = (iso: string) =>
    new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });

const STATUS_LABELS: Record<BillingStatus, string> = {
    free: 'Free',
    trialing: 'Trial',
    active: 'Active',
    past_due: 'Payment failed',
    cancelling: 'Cancelling',
    custom: 'Custom plan',
};

export default function Billing({
    billing,
    plans,
    seats,
    stripeEnabled,
    hasSubscription,
    isCustomer,
    canManage,
    checkout,
    errors,
}: Props) {
    const organization = useCurrentOrganization();
    const [interval, setInterval] = useState<Interval>(
        billing.interval ?? 'monthly',
    );
    const [processing, setProcessing] = useState(false);
    const slug = organization.slug;

    const choose = (plan: string) =>
        router.post(
            billingRoutes.subscribe.url({ organization: slug }),
            { plan, interval },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );

    const openPortal = () =>
        router.post(billingRoutes.portal.url({ organization: slug }));

    return (
        <>
            <Head title="Billing" />

            <div className="max-w-5xl space-y-10 px-4 py-6">
                <Heading
                    title="Billing"
                    description={`Plan and payments for ${organization.name}`}
                />

                {checkout === 'success' && (
                    <Notice tone="success">
                        Thanks! Your subscription is being activated. This can
                        take a few seconds; refresh if your plan hasn't changed
                        yet.
                    </Notice>
                )}
                {checkout === 'cancelled' && (
                    <Notice>
                        Checkout was cancelled. Nothing was charged.
                    </Notice>
                )}
                {billing.status === 'past_due' && (
                    <Notice tone="danger">
                        Your last payment failed. We'll retry automatically;
                        update your payment method to keep your plan.
                    </Notice>
                )}
                {canManage && !stripeEnabled && (
                    <Notice>
                        Payments aren't set up yet. Add your Stripe keys to .env
                        to enable upgrades.
                    </Notice>
                )}

                <section className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-3 rounded-lg border p-5">
                        <div className="flex items-center justify-between">
                            <p className="text-sm text-muted-foreground">
                                Current plan
                            </p>
                            <Badge
                                variant={
                                    billing.status === 'past_due'
                                        ? 'destructive'
                                        : 'secondary'
                                }
                            >
                                {STATUS_LABELS[billing.status]}
                            </Badge>
                        </div>
                        <p className="text-2xl font-semibold">
                            {billing.plan_name}
                            {billing.interval && (
                                <span className="ml-2 text-sm font-normal text-muted-foreground">
                                    billed {billing.interval}
                                </span>
                            )}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {billing.status === 'trialing' &&
                                billing.trial_ends_at &&
                                `Trial ends ${formatDate(billing.trial_ends_at)} (${billing.trial_days_left} days left). After that you move to the free plan unless you choose one.`}
                            {billing.status === 'cancelling' &&
                                billing.ends_at &&
                                `Cancelled. Access continues until ${formatDate(billing.ends_at)}.`}
                            {billing.status === 'free' &&
                                'Upgrade to unlock more seats and features.'}
                            {billing.status === 'custom' &&
                                'Your plan was set up by our team. Contact support to change it.'}
                        </p>
                        {billing.payment_method && (
                            <p className="flex items-center gap-2 text-sm">
                                <CreditCard className="size-4" />
                                <span className="capitalize">
                                    {billing.payment_method.brand}
                                </span>
                                {billing.payment_method.last_four &&
                                    `•••• ${billing.payment_method.last_four}`}
                            </p>
                        )}
                        {canManage && stripeEnabled && isCustomer && (
                            <Button variant="outline" onClick={openPortal}>
                                Manage payment & invoices
                            </Button>
                        )}
                    </div>

                    <div className="space-y-3 rounded-lg border p-5">
                        <p className="text-sm text-muted-foreground">Usage</p>
                        <UsageBar
                            label="Seats (members + pending invites)"
                            used={seats.used}
                            limit={seats.limit}
                        />
                    </div>
                </section>

                <section className="space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <Heading variant="small" title="Plans" />
                        <div
                            className="inline-flex rounded-md border p-1"
                            role="radiogroup"
                            aria-label="Billing interval"
                        >
                            {(['monthly', 'yearly'] as const).map((value) => (
                                <button
                                    key={value}
                                    type="button"
                                    role="radio"
                                    aria-checked={interval === value}
                                    onClick={() => setInterval(value)}
                                    className={cn(
                                        'rounded px-3 py-1 text-sm capitalize',
                                        interval === value &&
                                            'bg-primary text-primary-foreground',
                                    )}
                                >
                                    {value}
                                </button>
                            ))}
                        </div>
                    </div>

                    <InputError message={errors?.plan} />

                    <div className="grid gap-4 md:grid-cols-3">
                        {plans.map((plan) => {
                            const price = plan.prices.find(
                                (p) => p.interval === interval,
                            );
                            const isCurrent =
                                plan.key === billing.plan &&
                                (plan.prices.length === 0 ||
                                    billing.interval === interval ||
                                    !hasSubscription);
                            const seatsLimit = plan.limits.seats;

                            return (
                                <div
                                    key={plan.key}
                                    className={cn(
                                        'flex flex-col gap-4 rounded-lg border p-5',
                                        plan.key === billing.plan &&
                                            'border-primary',
                                    )}
                                >
                                    <div>
                                        <p className="font-semibold">
                                            {plan.name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {plan.description}
                                        </p>
                                    </div>
                                    <p className="text-2xl font-semibold">
                                        {price ? price.formatted : '$0'}
                                        <span className="text-sm font-normal text-muted-foreground">
                                            {price &&
                                                (interval === 'monthly'
                                                    ? ' / month'
                                                    : ' / year')}
                                            {price &&
                                                plan.per_seat &&
                                                ' per seat'}
                                        </span>
                                    </p>
                                    <ul className="flex-1 space-y-2 text-sm">
                                        <li className="flex gap-2">
                                            <Check className="size-4 shrink-0" />
                                            {seatsLimit === null ||
                                            seatsLimit === undefined
                                                ? 'Unlimited seats'
                                                : `Up to ${seatsLimit} seats`}
                                        </li>
                                        {plan.features.map((feature) => (
                                            <li
                                                key={feature}
                                                className="flex gap-2"
                                            >
                                                <Check className="size-4 shrink-0" />
                                                {featureLabel(feature)}
                                            </li>
                                        ))}
                                    </ul>
                                    {price &&
                                        canManage &&
                                        (isCurrent && hasSubscription ? (
                                            <Button
                                                variant="secondary"
                                                disabled
                                            >
                                                Current plan
                                            </Button>
                                        ) : (
                                            <Button
                                                disabled={
                                                    processing ||
                                                    !stripeEnabled ||
                                                    !price.available ||
                                                    billing.status === 'custom'
                                                }
                                                onClick={() => choose(plan.key)}
                                            >
                                                {hasSubscription
                                                    ? `Switch to ${plan.name}`
                                                    : `Choose ${plan.name}`}
                                            </Button>
                                        ))}
                                </div>
                            );
                        })}
                    </div>
                    {!canManage && (
                        <p className="text-sm text-muted-foreground">
                            Only owners can change the plan.
                        </p>
                    )}
                </section>

                {stripeEnabled && isCustomer && (
                    <section className="space-y-4">
                        <Heading variant="small" title="Invoices" />
                        <Deferred
                            data="invoices"
                            fallback={<Skeleton className="h-24 w-full" />}
                        >
                            <InvoiceList />
                        </Deferred>
                    </section>
                )}
            </div>
        </>
    );
}

function InvoiceList({ invoices = [] }: { invoices?: InvoiceRow[] }) {
    if (invoices.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">No invoices yet.</p>
        );
    }

    return (
        <ul className="divide-y rounded-lg border">
            {invoices.map((invoice) => (
                <li
                    key={invoice.id}
                    className="flex items-center justify-between gap-3 p-4 text-sm"
                >
                    <span>{formatDate(invoice.date)}</span>
                    <span className="text-muted-foreground capitalize">
                        {invoice.status}
                    </span>
                    <span className="font-medium">{invoice.total}</span>
                    {invoice.url ? (
                        <a
                            href={invoice.url}
                            target="_blank"
                            rel="noreferrer noopener"
                            className="underline underline-offset-4"
                        >
                            View
                        </a>
                    ) : (
                        <span />
                    )}
                </li>
            ))}
        </ul>
    );
}

function UsageBar({
    label,
    used,
    limit,
}: {
    label: string;
    used: number;
    limit: number | null;
}) {
    const percent = limit ? Math.min(100, Math.round((used / limit) * 100)) : 0;

    return (
        <div className="space-y-2">
            <div className="flex justify-between text-sm">
                <span>{label}</span>
                <span className="font-medium">
                    {used}
                    {limit !== null ? ` / ${limit}` : ' (unlimited)'}
                </span>
            </div>
            {limit !== null && (
                <div
                    className="h-2 overflow-hidden rounded-full bg-muted"
                    role="progressbar"
                    aria-valuenow={used}
                    aria-valuemax={limit}
                    aria-label={label}
                >
                    <div
                        className={cn(
                            'h-full rounded-full bg-primary',
                            used >= limit && 'bg-destructive',
                        )}
                        style={{ width: `${percent}%` }}
                    />
                </div>
            )}
        </div>
    );
}

function Notice({
    tone = 'neutral',
    children,
}: {
    tone?: 'neutral' | 'success' | 'danger';
    children: React.ReactNode;
}) {
    return (
        <p
            className={cn(
                'rounded-md border p-4 text-sm',
                tone === 'neutral' && 'bg-muted/50 text-muted-foreground',
                tone === 'success' &&
                    'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200',
                tone === 'danger' &&
                    'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-200',
            )}
        >
            {children}
        </p>
    );
}

Billing.layout = (props: SharedPageProps) => ({
    breadcrumbs: [
        {
            title: 'Billing',
            href: billingRoutes.show({
                organization: organizationSlug(props),
            }),
        },
    ],
});
