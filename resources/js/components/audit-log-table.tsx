import type { AuditEntry } from '@/types';

const formatTime = (iso: string) =>
    new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

const formatValue = (value: unknown): string => {
    if (value === null || value === undefined) {
        return '—';
    }

    if (typeof value === 'string') {
        return value;
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return value.toString();
    }

    return JSON.stringify(value) ?? '';
};

export function AuditLogTable({
    entries,
    showOrganization = false,
}: {
    entries: AuditEntry[];
    showOrganization?: boolean;
}) {
    if (entries.length === 0) {
        return (
            <p className="rounded-lg border p-6 text-center text-sm text-muted-foreground">
                No activity recorded yet.
            </p>
        );
    }

    return (
        <ul className="divide-y rounded-lg border text-sm">
            {entries.map((entry) => (
                <li
                    key={entry.public_id}
                    className="grid gap-1 p-4 md:grid-cols-[11rem_1fr_auto] md:gap-4"
                >
                    <time
                        dateTime={entry.created_at}
                        className="text-muted-foreground"
                    >
                        {formatTime(entry.created_at)}
                    </time>
                    <div className="min-w-0 space-y-1">
                        <p>
                            <code className="rounded bg-muted px-1.5 py-0.5 text-xs">
                                {entry.action}
                            </code>
                            {entry.subject_label && (
                                <span className="ml-2">
                                    {entry.subject_label}
                                </span>
                            )}
                            {showOrganization && entry.organization && (
                                <span className="ml-2 text-muted-foreground">
                                    in {entry.organization}
                                </span>
                            )}
                        </p>
                        {Object.keys(entry.metadata).length > 0 && (
                            <dl className="flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-muted-foreground">
                                {Object.entries(entry.metadata).map(
                                    ([key, value]) => (
                                        <div key={key} className="flex gap-1">
                                            <dt>{key}:</dt>
                                            <dd className="break-all">
                                                {formatValue(value)}
                                            </dd>
                                        </div>
                                    ),
                                )}
                            </dl>
                        )}
                    </div>
                    <div className="text-muted-foreground md:text-right">
                        <p>{entry.actor ?? 'System'}</p>
                        {entry.impersonator && (
                            <p className="text-xs text-amber-600 dark:text-amber-400">
                                via {entry.impersonator} (impersonating)
                            </p>
                        )}
                        {entry.ip_address && (
                            <p className="text-xs">{entry.ip_address}</p>
                        )}
                    </div>
                </li>
            ))}
        </ul>
    );
}
