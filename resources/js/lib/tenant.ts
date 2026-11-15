import type { SharedPageProps } from '@/types';

/**
 * Slug of the current organization from page props. For use outside React
 * components, e.g. in `Page.layout = (props) => ({ breadcrumbs })` callbacks.
 */
export function organizationSlug(props: SharedPageProps): string {
    if (!props.currentOrganization) {
        throw new Error('organizationSlug() can only be used on tenant pages.');
    }

    return props.currentOrganization.slug;
}
