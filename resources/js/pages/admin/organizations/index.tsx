import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import admin from '@/routes/admin';
import type { Paginated } from '@/types';

type Row = {
    public_id: string;
    name: string;
    slug: string;
    status: string;
    plan: string;
    billing_status: string;
    members: number;
    created_at: string | null;
};

type Props = {
    organizations: Paginated<Row>;
    filters: { q: string; status: string };
};

export default function AdminOrganizations({ organizations, filters }: Props) {
    return (
        <>
            <Head title="Organizations · Admin" />
            <div className="space-y-6">
                <Heading
                    title="Organizations"
                    description="Search by name, URL, public id, Stripe customer id or member email"
                />
                <Form
                    {...admin.organizations.index.form()}
                    className="flex flex-wrap gap-2"
                >
                    <Input
                        name="q"
                        defaultValue={filters.q}
                        placeholder="Search…"
                        className="max-w-sm"
                    />
                    <select
                        name="status"
                        defaultValue={filters.status}
                        className="rounded-md border bg-background px-3 text-sm"
                        aria-label="Status"
                    >
                        <option value="">Any status</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="pending_deletion">
                            Pending deletion
                        </option>
                    </select>
                    <Button variant="outline">Search</Button>
                </Form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="p-3 font-medium">Name</th>
                                <th className="p-3 font-medium">Status</th>
                                <th className="p-3 font-medium">Plan</th>
                                <th className="p-3 font-medium">Members</th>
                                <th className="p-3 font-medium">Created</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {organizations.data.map((organization) => (
                                <tr key={organization.public_id}>
                                    <td className="p-3">
                                        <Link
                                            href={admin.organizations.show(
                                                organization.public_id,
                                            )}
                                            className="font-medium underline-offset-4 hover:underline"
                                        >
                                            {organization.name}
                                        </Link>
                                        <p className="text-xs text-muted-foreground">
                                            /o/{organization.slug}
                                        </p>
                                    </td>
                                    <td className="p-3">
                                        <Badge
                                            variant={
                                                organization.status === 'active'
                                                    ? 'secondary'
                                                    : 'destructive'
                                            }
                                        >
                                            {organization.status}
                                        </Badge>
                                    </td>
                                    <td className="p-3">
                                        {organization.plan}
                                        <span className="ml-1 text-xs text-muted-foreground">
                                            ({organization.billing_status})
                                        </span>
                                    </td>
                                    <td className="p-3 tabular-nums">
                                        {organization.members}
                                    </td>
                                    <td className="p-3 text-muted-foreground">
                                        {organization.created_at &&
                                            new Date(
                                                organization.created_at,
                                            ).toLocaleDateString()}
                                    </td>
                                </tr>
                            ))}
                            {organizations.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        No organizations found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination paginator={organizations} />
            </div>
        </>
    );
}
