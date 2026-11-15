import { Form, Head } from '@inertiajs/react';
import { AuditLogTable } from '@/components/audit-log-table';
import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import admin from '@/routes/admin';
import type { AuditEntry, Paginated } from '@/types';

export default function AdminAuditLog({
    entries,
    filters,
}: {
    entries: Paginated<AuditEntry>;
    filters: { action: string; actor: string };
}) {
    return (
        <>
            <Head title="Audit log · Admin" />
            <div className="space-y-6">
                <Heading
                    title="Audit log"
                    description="Every organization and platform-level entry"
                />
                <Form
                    {...admin.auditLog.index.form()}
                    className="flex flex-wrap gap-2"
                >
                    <Input
                        name="action"
                        defaultValue={filters.action}
                        placeholder="Action prefix, e.g. admin. or auth.login"
                        className="max-w-xs"
                    />
                    <Input
                        name="actor"
                        defaultValue={filters.actor}
                        placeholder="Actor name or email"
                        className="max-w-xs"
                    />
                    <Button variant="outline">Filter</Button>
                </Form>
                <AuditLogTable entries={entries.data} showOrganization />
                <Pagination paginator={entries} />
            </div>
        </>
    );
}
