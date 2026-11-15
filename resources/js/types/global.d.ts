import type { Impersonation } from '@/types/admin';
import type { Auth } from '@/types/auth';
import type {
    CurrentOrganization,
    OrganizationSummary,
} from '@/types/organization';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            currentOrganization: CurrentOrganization | null;
            organizations: OrganizationSummary[];
            impersonation: Impersonation | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
