import { usePage } from '@inertiajs/react';
import type { CurrentOrganization } from '@/types';

/**
 * The organization for the current tenant page (/o/{slug}/...).
 *
 * Pass `{ organization: org.slug }` explicitly to tenant route helpers rather
 * than relying on URL defaults, so links never point at a stale organization.
 */
export function useCurrentOrganization(): CurrentOrganization {
    const { currentOrganization } = usePage().props;

    if (!currentOrganization) {
        throw new Error(
            'useCurrentOrganization() can only be used on tenant pages.',
        );
    }

    return currentOrganization;
}

export function useCan(permission: string): boolean {
    const { currentOrganization } = usePage().props;
    const permissions = currentOrganization?.permissions ?? [];

    return permissions.includes('*') || permissions.includes(permission);
}

/**
 * Whether the current organization's plan includes a feature (UI hint only).
 */
export function useFeature(feature: string): boolean {
    const { currentOrganization } = usePage().props;

    return currentOrganization?.billing.features.includes(feature) ?? false;
}

/**
 * Whether a feature flag is active for the current organization (Pennant).
 */
export function useFlag(flag: string): boolean {
    const { currentOrganization } = usePage().props;

    return currentOrganization?.flags.includes(flag) ?? false;
}
