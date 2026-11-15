import { Link, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown, Plus } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useIsMobile } from '@/hooks/use-mobile';
import { create } from '@/routes/organizations';
import { dashboard } from '@/routes/tenant';

export function OrganizationSwitcher() {
    const { currentOrganization, organizations } = usePage().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="data-[state=open]:bg-sidebar-accent"
                            data-test="organization-switcher"
                        >
                            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                                <Building2 className="size-4" />
                            </div>
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-semibold">
                                    {currentOrganization?.name ??
                                        'Select organization'}
                                </span>
                                {currentOrganization?.role && (
                                    <span className="truncate text-xs text-muted-foreground capitalize">
                                        {currentOrganization.role}
                                    </span>
                                )}
                            </div>
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        align="start"
                        side={
                            isMobile
                                ? 'bottom'
                                : state === 'collapsed'
                                  ? 'right'
                                  : 'bottom'
                        }
                    >
                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                            Organizations
                        </DropdownMenuLabel>
                        {organizations.map((organization) => (
                            <DropdownMenuItem
                                key={organization.public_id}
                                asChild
                            >
                                <Link
                                    href={dashboard({
                                        organization: organization.slug,
                                    })}
                                    className="flex w-full cursor-pointer items-center"
                                >
                                    <span className="truncate">
                                        {organization.name}
                                    </span>
                                    {organization.public_id ===
                                        currentOrganization?.public_id && (
                                        <Check className="ml-auto size-4" />
                                    )}
                                </Link>
                            </DropdownMenuItem>
                        ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link
                                href={create()}
                                className="flex w-full cursor-pointer items-center"
                            >
                                <Plus className="mr-2 size-4" />
                                Create organization
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
