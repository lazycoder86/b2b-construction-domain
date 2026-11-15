import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CreditCard,
    FolderGit2,
    LayoutGrid,
    ScrollText,
    Settings,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { OrganizationSwitcher } from '@/components/organization-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import tenant from '@/routes/tenant';
import type { NavItem } from '@/types';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { currentOrganization, organizations } = usePage().props;
    const permissions = currentOrganization?.permissions ?? [];
    const can = (permission: string) =>
        permissions.includes('*') || permissions.includes(permission);
    const canViewBilling = can('billing.view');

    // Tenant links only exist inside an organization (e.g. not on /settings/profile).
    const mainNavItems: NavItem[] = currentOrganization
        ? [
              {
                  title: 'Dashboard',
                  href: tenant.dashboard({
                      organization: currentOrganization.slug,
                  }),
                  icon: LayoutGrid,
              },
              {
                  title: 'Members',
                  href: tenant.members.index({
                      organization: currentOrganization.slug,
                  }),
                  icon: Users,
              },
              ...(canViewBilling
                  ? [
                        {
                            title: 'Billing',
                            href: tenant.billing.show({
                                organization: currentOrganization.slug,
                            }),
                            icon: CreditCard,
                        },
                    ]
                  : []),
              ...(can('audit.view')
                  ? [
                        {
                            title: 'Audit log',
                            href: tenant.auditLog.index({
                                organization: currentOrganization.slug,
                            }),
                            icon: ScrollText,
                        },
                    ]
                  : []),
              {
                  title: 'Organization settings',
                  href: tenant.settings.edit({
                      organization: currentOrganization.slug,
                  }),
                  icon: Settings,
              },
          ]
        : [
              {
                  title: 'Dashboard',
                  href: dashboard(),
                  icon: LayoutGrid,
              },
          ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                {organizations.length > 0 && <OrganizationSwitcher />}
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
