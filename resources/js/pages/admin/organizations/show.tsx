import { Form, Head, Link, router } from '@inertiajs/react';
import { AuditLogTable } from '@/components/audit-log-table';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import admin from '@/routes/admin';
import type { AuditEntry, OrganizationBilling } from '@/types';

type Props = {
    organization: {
        public_id: string;
        name: string;
        slug: string;
        status: 'active' | 'suspended' | 'pending_deletion';
        require_two_factor: boolean;
        stripe_id: string | null;
        trial_ends_at: string | null;
        deletion_scheduled_at: string | null;
        created_at: string | null;
        entitlement_overrides: {
            plan?: string;
            features?: string[];
            limits?: { seats?: number | null };
        };
    };
    entitlements: OrganizationBilling;
    subscription: {
        stripe_id: string;
        status: string;
        price: string | null;
        quantity: number | null;
        ends_at: string | null;
    } | null;
    members: { public_id: string; name: string; email: string; role: string }[];
    pendingInvitations: number;
    flags: {
        key: string;
        description: string;
        active: boolean;
        default: boolean;
    }[];
    plans: { key: string; name: string }[];
    audit: AuditEntry[];
};

const date = (iso: string | null) =>
    iso
        ? new Date(iso).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';

export default function AdminOrganization({
    organization,
    entitlements,
    subscription,
    members,
    pendingInvitations,
    flags,
    plans,
    audit,
}: Props) {
    const id = organization.public_id;
    const overrides = organization.entitlement_overrides;

    return (
        <>
            <Head title={`${organization.name} · Admin`} />
            <div className="max-w-5xl space-y-10">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={organization.name}
                        description={`/o/${organization.slug} · ${organization.public_id}`}
                    />
                    <Badge
                        variant={
                            organization.status === 'active'
                                ? 'secondary'
                                : 'destructive'
                        }
                    >
                        {organization.status}
                    </Badge>
                </div>

                <dl className="grid gap-4 text-sm md:grid-cols-3">
                    <Fact label="Plan">
                        {entitlements.plan_name} ({entitlements.status})
                    </Fact>
                    <Fact label="Trial ends">
                        {date(organization.trial_ends_at)}
                    </Fact>
                    <Fact label="Stripe customer">
                        {organization.stripe_id ?? '—'}
                    </Fact>
                    <Fact label="Seats limit">
                        {entitlements.limits.seats ?? 'Unlimited'}
                    </Fact>
                    <Fact label="Members / pending invites">
                        {members.length} / {pendingInvitations}
                    </Fact>
                    <Fact label="Require 2FA">
                        {organization.require_two_factor ? 'Yes' : 'No'}
                    </Fact>
                    <Fact label="Created">{date(organization.created_at)}</Fact>
                    {organization.deletion_scheduled_at && (
                        <Fact label="Purge scheduled">
                            {date(organization.deletion_scheduled_at)}
                        </Fact>
                    )}
                    {subscription && (
                        <Fact label="Subscription">
                            {subscription.status} · {subscription.price} ×{' '}
                            {subscription.quantity ?? 1}
                            {subscription.ends_at &&
                                ` · ends ${date(subscription.ends_at)}`}
                        </Fact>
                    )}
                </dl>

                <section className="grid gap-6 md:grid-cols-2">
                    {organization.status !== 'pending_deletion' && (
                        <Form
                            {...admin.organizations.status.form(id)}
                            options={{ preserveScroll: true }}
                            resetOnSuccess
                            className="space-y-3 rounded-lg border p-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Heading
                                        variant="small"
                                        title={
                                            organization.status === 'active'
                                                ? 'Suspend'
                                                : 'Reactivate'
                                        }
                                        description={
                                            organization.status === 'active'
                                                ? 'Members lose access immediately. Billing is not touched.'
                                                : 'Members regain access.'
                                        }
                                    />
                                    <input
                                        type="hidden"
                                        name="status"
                                        value={
                                            organization.status === 'active'
                                                ? 'suspended'
                                                : 'active'
                                        }
                                    />
                                    <Input
                                        name="reason"
                                        placeholder="Reason (required, audited)"
                                    />
                                    <InputError message={errors.reason} />
                                    <Button
                                        variant={
                                            organization.status === 'active'
                                                ? 'destructive'
                                                : 'default'
                                        }
                                        disabled={processing}
                                    >
                                        {organization.status === 'active'
                                            ? 'Suspend organization'
                                            : 'Reactivate organization'}
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}

                    <Form
                        {...admin.organizations.trial.form(id)}
                        options={{ preserveScroll: true }}
                        className="space-y-3 rounded-lg border p-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Heading
                                    variant="small"
                                    title="Extend trial"
                                    description="Adds days to the current trial (or starts one)."
                                />
                                <Input
                                    name="days"
                                    type="number"
                                    min={1}
                                    max={90}
                                    defaultValue={14}
                                />
                                <InputError message={errors.days} />
                                <Button variant="outline" disabled={processing}>
                                    Extend trial
                                </Button>
                            </>
                        )}
                    </Form>

                    <Form
                        {...admin.organizations.entitlements.form(id)}
                        options={{ preserveScroll: true }}
                        transform={(data) => ({
                            ...data,
                            plan: data.plan || null,
                            seats: data.seats || null,
                            features: String(data.features ?? '')
                                .split(',')
                                .map((feature) => feature.trim())
                                .filter(Boolean),
                        })}
                        className="space-y-3 rounded-lg border p-4 md:col-span-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Heading
                                    variant="small"
                                    title="Entitlement overrides"
                                    description="Sales/support exceptions. A plan here wins over the subscription."
                                />
                                <div className="grid gap-3 md:grid-cols-3">
                                    <div className="grid gap-1">
                                        <Label htmlFor="override-plan">
                                            Plan
                                        </Label>
                                        <select
                                            id="override-plan"
                                            name="plan"
                                            defaultValue={overrides.plan ?? ''}
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
                                        >
                                            <option value="">
                                                No override
                                            </option>
                                            {plans.map((plan) => (
                                                <option
                                                    key={plan.key}
                                                    value={plan.key}
                                                >
                                                    {plan.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.plan} />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="override-features">
                                            Extra features
                                        </Label>
                                        <Input
                                            id="override-features"
                                            name="features"
                                            placeholder="api_access, webhooks"
                                            defaultValue={(
                                                overrides.features ?? []
                                            ).join(', ')}
                                        />
                                        <InputError
                                            message={
                                                errors.features ??
                                                errors['features.0']
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="override-seats">
                                            Seat limit
                                        </Label>
                                        <Input
                                            id="override-seats"
                                            name="seats"
                                            type="number"
                                            min={1}
                                            placeholder="Plan default"
                                            defaultValue={
                                                overrides.limits?.seats ?? ''
                                            }
                                        />
                                        <InputError message={errors.seats} />
                                    </div>
                                </div>
                                <Input
                                    name="reason"
                                    placeholder="Reason (required, audited)"
                                />
                                <InputError message={errors.reason} />
                                <Button variant="outline" disabled={processing}>
                                    Save overrides
                                </Button>
                            </>
                        )}
                    </Form>
                </section>

                <section className="space-y-3">
                    <Heading variant="small" title="Feature flags" />
                    <ul className="divide-y rounded-lg border text-sm">
                        {flags.map((flag) => (
                            <li
                                key={flag.key}
                                className="flex flex-wrap items-center gap-3 p-4"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium">
                                        <code>{flag.key}</code>{' '}
                                        <Badge
                                            variant={
                                                flag.active
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {flag.active ? 'on' : 'off'}
                                        </Badge>
                                    </p>
                                    <p className="text-muted-foreground">
                                        {flag.description} Default:{' '}
                                        {flag.default ? 'on' : 'off'}.
                                    </p>
                                </div>
                                {(['on', 'off', 'default'] as const).map(
                                    (state) => (
                                        <Button
                                            key={state}
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.put(
                                                    admin.organizations.flags.url(
                                                        {
                                                            organization: id,
                                                            flag: flag.key,
                                                        },
                                                    ),
                                                    { state },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {state === 'default'
                                                ? 'Reset'
                                                : `Turn ${state}`}
                                        </Button>
                                    ),
                                )}
                            </li>
                        ))}
                    </ul>
                </section>

                <section className="space-y-3">
                    <Heading variant="small" title="Members" />
                    <ul className="divide-y rounded-lg border text-sm">
                        {members.map((member) => (
                            <li
                                key={member.public_id}
                                className="flex items-center justify-between p-3"
                            >
                                <Link
                                    href={admin.users.show(member.public_id)}
                                    className="underline-offset-4 hover:underline"
                                >
                                    {member.name}{' '}
                                    <span className="text-muted-foreground">
                                        {member.email}
                                    </span>
                                </Link>
                                <Badge variant="secondary">{member.role}</Badge>
                            </li>
                        ))}
                    </ul>
                </section>

                <section className="space-y-3">
                    <Heading variant="small" title="Recent activity" />
                    <AuditLogTable entries={audit} />
                </section>
            </div>
        </>
    );
}

function Fact({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="rounded-lg border p-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-medium break-all">{children}</dd>
        </div>
    );
}
