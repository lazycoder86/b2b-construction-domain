import { Head } from '@inertiajs/react';
import { AuditLogTable } from '@/components/audit-log-table';
import Heading from '@/components/heading';
import type { AuditEntry } from '@/types';

type Props = {
    stats: Record<string, number>;
    recent: AuditEntry[];
};

const LABELS: Record<string, string> = {
    organizations: 'Organizations',
    active_organizations: 'Active',
    suspended_organizations: 'Suspended',
    pending_deletion: 'Pending deletion',
    trialing: 'On trial',
    paying: 'Paying',
    users: 'Users',
    new_users_7d: 'New users (7 days)',
};

export default function AdminDashboard({ stats, recent }: Props) {
    return (
        <>
            <Head title="Admin" />
            <div className="space-y-8">
                <Heading title="Overview" />
                <dl className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    {Object.entries(stats).map(([key, value]) => (
                        <div key={key} className="rounded-lg border p-4">
                            <dt className="text-sm text-muted-foreground">
                                {LABELS[key] ?? key}
                            </dt>
                            <dd className="text-2xl font-semibold tabular-nums">
                                {value.toLocaleString()}
                            </dd>
                        </div>
                    ))}
                </dl>
                <section className="space-y-3">
                    <Heading variant="small" title="Recent activity" />
                    <AuditLogTable entries={recent} showOrganization />
                </section>
            </div>
        </>
    );
}
