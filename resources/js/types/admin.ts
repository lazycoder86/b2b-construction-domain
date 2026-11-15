export type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

export type AuditEntry = {
    public_id: string;
    action: string;
    actor: string | null;
    actor_email: string | null;
    impersonator: string | null;
    subject_type: string | null;
    subject_label: string | null;
    metadata: Record<string, unknown>;
    ip_address: string | null;
    created_at: string;
    /** Admin views only. */
    organization?: string | null;
};

export type Impersonation = {
    impersonator: string | null;
    expires_at: string | null;
};
