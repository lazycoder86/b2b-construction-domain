import { Link } from '@inertiajs/react';
import { Building2, LayoutGrid, ScrollText, Users } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { dashboard as appDashboard } from '@/routes';
import admin from '@/routes/admin';

const items = [
    {
        title: 'Overview',
        href: admin.dashboard(),
        icon: LayoutGrid,
        exact: true,
    },
    {
        title: 'Organizations',
        href: admin.organizations.index(),
        icon: Building2,
    },
    { title: 'Users', href: admin.users.index(), icon: Users },
    { title: 'Audit log', href: admin.auditLog.index(), icon: ScrollText },
];

/**
 * Internal admin area. Deliberately separate from the tenant app shell.
 */
export default function AdminLayout({ children }: PropsWithChildren) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="min-h-screen bg-background">
            <header className="flex items-center justify-between border-b bg-zinc-900 px-6 py-3 text-zinc-50">
                <div className="flex items-center gap-3">
                    <span className="rounded bg-red-500 px-2 py-0.5 text-xs font-semibold tracking-wide uppercase">
                        Internal
                    </span>
                    <span className="font-semibold">Admin</span>
                </div>
                <Link
                    href={appDashboard()}
                    className="text-sm underline-offset-4 hover:underline"
                >
                    Back to app
                </Link>
            </header>
            <div className="flex flex-col md:flex-row">
                <nav className="flex gap-1 border-b p-3 md:min-h-[calc(100vh-49px)] md:w-56 md:flex-col md:border-r md:border-b-0">
                    {items.map((item) => {
                        const active = item.exact
                            ? isCurrentUrl(item.href)
                            : isCurrentOrParentUrl(item.href);

                        return (
                            <Link
                                key={item.title}
                                href={item.href}
                                className={cn(
                                    'flex items-center gap-2 rounded-md px-3 py-2 text-sm hover:bg-muted',
                                    active && 'bg-muted font-medium',
                                )}
                            >
                                <item.icon className="size-4" />
                                {item.title}
                            </Link>
                        );
                    })}
                </nav>
                <main className="min-w-0 flex-1 p-6">{children}</main>
            </div>
        </div>
    );
}
