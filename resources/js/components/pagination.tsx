import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

/**
 * Page links for a Laravel paginator (`->paginate()->withQueryString()`).
 */
export function Pagination({ paginator }: { paginator: Paginated<unknown> }) {
    if (paginator.last_page <= 1) {
        return null;
    }

    return (
        <nav
            className="flex flex-wrap items-center gap-1 text-sm"
            aria-label="Pagination"
        >
            <span className="mr-2 text-muted-foreground">
                {paginator.from}–{paginator.to} of {paginator.total}
            </span>
            {paginator.links.map((link, index) => {
                // Laravel labels: "&laquo; Previous", "1", "...", "Next &raquo;"
                const label = link.label
                    .replace('&laquo;', '‹')
                    .replace('&raquo;', '›');

                return link.url ? (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        className={cn(
                            'rounded px-2.5 py-1 hover:bg-muted',
                            link.active &&
                                'bg-primary text-primary-foreground hover:bg-primary',
                        )}
                    >
                        {label}
                    </Link>
                ) : (
                    <span
                        key={index}
                        className="px-2.5 py-1 text-muted-foreground"
                    >
                        {label}
                    </span>
                );
            })}
        </nav>
    );
}
