export type OrganizationRole = 'owner' | 'admin' | 'member' | 'viewer';

export type OrganizationSummary = {
    public_id: string;
    name: string;
    slug: string;
};

export type BillingStatus =
    | 'free'
    | 'trialing'
    | 'active'
    | 'past_due'
    | 'cancelling'
    | 'custom';

export type OrganizationBilling = {
    plan: string;
    plan_name: string;
    status: BillingStatus;
    trial_ends_at: string | null;
    trial_days_left: number | null;
    features: string[];
    limits: Record<string, number | null>;
};

export type CurrentOrganization = OrganizationSummary & {
    role: OrganizationRole | null;
    /** UI hints only; the server re-checks every action. */
    permissions: string[];
    /** UI hints only; gate features server-side with the `feature:` middleware. */
    billing: OrganizationBilling;
    /** Active feature flags (config/features.php). */
    flags: string[];
};
