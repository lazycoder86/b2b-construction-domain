import { Form, Head, Link } from '@inertiajs/react';
import { AuditLogTable } from '@/components/audit-log-table';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import admin from '@/routes/admin';
import type { AuditEntry } from '@/types';

type Props = {
    user: {
        public_id: string;
        name: string;
        email: string;
        verified: boolean;
        has_mfa: boolean;
        is_platform_admin: boolean;
        created_at: string | null;
    };
    memberships: {
        organization: string;
        organization_public_id: string;
        status: string;
        role: string;
    }[];
    canImpersonate: boolean;
    audit: AuditEntry[];
};

export default function AdminUser({
    user,
    memberships,
    canImpersonate,
    audit,
}: Props) {
    return (
        <>
            <Head title={`${user.name} · Admin`} />
            <div className="max-w-4xl space-y-10">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title={user.name}
                        description={`${user.email} · ${user.public_id}`}
                    />
                    <div className="flex gap-2">
                        {user.is_platform_admin && <Badge>staff</Badge>}
                        <Badge
                            variant={user.verified ? 'secondary' : 'outline'}
                        >
                            {user.verified ? 'verified' : 'unverified'}
                        </Badge>
                        <Badge variant={user.has_mfa ? 'secondary' : 'outline'}>
                            {user.has_mfa ? 'MFA on' : 'no MFA'}
                        </Badge>
                    </div>
                </div>

                {canImpersonate && (
                    <Form
                        {...admin.users.impersonate.form(user.public_id)}
                        className="space-y-3 rounded-lg border border-amber-300 p-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Heading
                                    variant="small"
                                    title="Impersonate"
                                    description="Sign in as this user to reproduce an issue. Time-limited and fully audited; security and billing actions are blocked."
                                />
                                <Input
                                    name="reason"
                                    placeholder="Reason, e.g. ticket number (required)"
                                />
                                <InputError message={errors.reason} />
                                <Button variant="outline" disabled={processing}>
                                    Impersonate {user.name}
                                </Button>
                            </>
                        )}
                    </Form>
                )}

                <section className="space-y-3">
                    <Heading variant="small" title="Organizations" />
                    <ul className="divide-y rounded-lg border text-sm">
                        {memberships.map((membership) => (
                            <li
                                key={membership.organization_public_id}
                                className="flex items-center justify-between p-3"
                            >
                                <Link
                                    href={admin.organizations.show(
                                        membership.organization_public_id,
                                    )}
                                    className="underline-offset-4 hover:underline"
                                >
                                    {membership.organization}
                                </Link>
                                <span className="flex gap-2">
                                    {membership.status !== 'active' && (
                                        <Badge variant="destructive">
                                            {membership.status}
                                        </Badge>
                                    )}
                                    <Badge variant="secondary">
                                        {membership.role}
                                    </Badge>
                                </span>
                            </li>
                        ))}
                        {memberships.length === 0 && (
                            <li className="p-4 text-muted-foreground">
                                Not a member of any organization.
                            </li>
                        )}
                    </ul>
                </section>

                <section className="space-y-3">
                    <Heading
                        variant="small"
                        title="Activity"
                        description="Actions by this user, or performed by them while impersonating"
                    />
                    <AuditLogTable entries={audit} />
                </section>
            </div>
        </>
    );
}
