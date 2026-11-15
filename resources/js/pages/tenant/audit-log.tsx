import { Form, Head } from '@inertiajs/react';
import { AuditLogTable } from '@/components/audit-log-table';
import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCurrentOrganization } from '@/hooks/use-current-organization';
import { organizationSlug } from '@/lib/tenant';
import auditLog from '@/routes/tenant/audit-log';
import type { AuditEntry, Paginated, SharedPageProps } from '@/types';

type Props = {
    entries: Paginated<AuditEntry>;
    filters: { action: string; actor: string };
    actionGroups: string[];
};

export default function OrganizationAuditLog({
    entries,
    filters,
    actionGroups,
}: Props) {
    const organization = useCurrentOrganization();

    return (
        <>
            <Head title="Audit log" />
            <div className="max-w-5xl space-y-6 px-4 py-6">
                <Heading
                    title="Audit log"
                    description={`Security-relevant activity in ${organization.name}`}
                />
                <Form
                    {...auditLog.index.form({
                        organization: organization.slug,
                    })}
                    className="flex flex-wrap gap-2"
                >
                    <select
                        name="action"
                        defaultValue={filters.action}
                        aria-label="Type of activity"
                        className="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">All activity</option>
                        {actionGroups.map((group) => (
                            <option key={group} value={`${group}.`}>
                                {group.charAt(0).toUpperCase() + group.slice(1)}
                            </option>
                        ))}
                    </select>
                    <Input
                        name="actor"
                        defaultValue={filters.actor}
                        placeholder="Person (name or email)"
                        className="max-w-xs"
                    />
                    <Button variant="outline">Filter</Button>
                </Form>
                <AuditLogTable entries={entries.data} />
                <Pagination paginator={entries} />
            </div>
        </>
    );
}

OrganizationAuditLog.layout = (props: SharedPageProps) => ({
    breadcrumbs: [
        {
            title: 'Audit log',
            href: auditLog.index({ organization: organizationSlug(props) }),
        },
    ],
});
