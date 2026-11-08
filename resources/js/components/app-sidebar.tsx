import { Link } from '@inertiajs/react';
import { ClipboardList, FileQuestion, LayoutGrid, ScanSearch, LibraryBig, ShieldCheck, Workflow, GitCompareArrows } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
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
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    { title: 'SIFI Workbench', href: '/sifi/workbench', icon: Workflow },
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    { title: 'Signal Explorer', href: '/sifi/signals', icon: ScanSearch },
    { title: 'Signal Relations', href: '/sifi/signal-relations', icon: GitCompareArrows },
    { title: 'Investigations', href: '/sifi/investigations', icon: ClipboardList },
    { title: 'Issue Candidates', href: '/sifi/issue-candidates', icon: FileQuestion },
    { title: 'Evidence Reviews', href: '/sifi/evidence-reviews', icon: ShieldCheck },
    { title: 'Source & Coverage', href: '/sifi/source-catalog', icon: LibraryBig },
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
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
