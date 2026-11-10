import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

/** SIFI: avoid collapsed/expanded label clipping at sidebar transition boundaries. */
export function NavMain({ items }: { items: NavItem[] }) {
    const { isCurrentUrl } = useCurrentUrl();
    return (
        <SidebarGroup className="sifi-main-nav min-w-0 px-2 py-0">
            <SidebarGroupLabel className="min-w-0 px-3">
                PLATFORM
            </SidebarGroupLabel>
            <SidebarMenu className="min-w-0 gap-1">
                {items.map((item) => (
                    <SidebarMenuItem key={item.title} className="min-w-0">
                        <SidebarMenuButton
                            asChild
                            isActive={isCurrentUrl(item.href)}
                            tooltip={{ children: item.title }}
                            className="min-w-0 !gap-3 !px-3"
                        >
                            <Link href={item.href} prefetch title={item.title}>
                                {item.icon && (
                                    <item.icon
                                        className="size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                )}
                                <span className="sifi-nav-label min-w-0 flex-1 text-left">
                                    {item.title}
                                </span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
