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
    email: string;
    verified: boolean;
    is_platform_admin: boolean;
    organizations: number;
    created_at: string | null;
};

export default function AdminUsers({
    users,
    filters,
}: {
    users: Paginated<Row>;
    filters: { q: string };
}) {
    return (
        <>
            <Head title="Users · Admin" />
            <div className="space-y-6">
                <Heading
                    title="Users"
                    description="Search by name, email or public id"
                />
                <Form {...admin.users.index.form()} className="flex gap-2">
                    <Input
                        name="q"
                        defaultValue={filters.q}
                        placeholder="Search…"
                        className="max-w-sm"
                    />
                    <Button variant="outline">Search</Button>
                </Form>
                <ul className="divide-y rounded-lg border text-sm">
                    {users.data.map((user) => (
                        <li
                            key={user.public_id}
                            className="flex flex-wrap items-center gap-3 p-3"
                        >
                            <Link
                                href={admin.users.show(user.public_id)}
                                className="min-w-0 flex-1 underline-offset-4 hover:underline"
                            >
                                <span className="font-medium">{user.name}</span>{' '}
                                <span className="text-muted-foreground">
                                    {user.email}
                                </span>
                            </Link>
                            {user.is_platform_admin && <Badge>staff</Badge>}
                            {!user.verified && (
                                <Badge variant="outline">unverified</Badge>
                            )}
                            <span className="text-muted-foreground">
                                {user.organizations} org
                                {user.organizations === 1 ? '' : 's'}
                            </span>
                        </li>
                    ))}
                    {users.data.length === 0 && (
                        <li className="p-6 text-center text-muted-foreground">
                            No users found.
                        </li>
                    )}
                </ul>
                <Pagination paginator={users} />
            </div>
        </>
    );
}
