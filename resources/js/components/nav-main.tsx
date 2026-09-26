import { Link, usePage } from '@inertiajs/react';
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuItem, SidebarMenuButton, SidebarMenuSub, SidebarMenuSubItem, SidebarMenuSubButton } from '@/components/ui/sidebar';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import {
    BarChart3,
    Bell,
    Boxes,
    CalendarDays,
    ChevronDown,
    CircleDot,
    Contact,
    FileText,
    FolderKanban,
    LayoutDashboard,
    ListChecks,
    Mail,
    Package,
    ReceiptText,
    Settings,
    ShieldCheck,
    Target,
    UserCog,
    UserRound,
    UsersRound,
    WalletCards,
} from 'lucide-react';
import { NavItem } from '@/types';

const fallbackSubmenuIcons = [
    { pattern: /dashboard|overview|home/i, icon: LayoutDashboard },
    { pattern: /role|permission/i, icon: ShieldCheck },
    { pattern: /profile|user setting|user account/i, icon: UserCog },
    { pattern: /users?|employee|staff|member/i, icon: UserRound },
    { pattern: /customer|client|vendor|supplier/i, icon: UsersRound },
    { pattern: /contact|lead/i, icon: Contact },
    { pattern: /report|analytic|summary/i, icon: BarChart3 },
    { pattern: /invoice|bill|payment|expense|revenue|transaction|account/i, icon: ReceiptText },
    { pattern: /bank|wallet|credit|debit/i, icon: WalletCards },
    { pattern: /project|task|milestone|production/i, icon: FolderKanban },
    { pattern: /setting|setup|configuration/i, icon: Settings },
    { pattern: /document|template|proposal|quotation|contract/i, icon: FileText },
    { pattern: /list|request|order|record/i, icon: ListChecks },
    { pattern: /product|item|service|stock/i, icon: Package },
    { pattern: /inventory|warehouse|category|unit/i, icon: Boxes },
    { pattern: /calendar|event|meeting|schedule/i, icon: CalendarDays },
    { pattern: /email|mail/i, icon: Mail },
    { pattern: /notification|announcement/i, icon: Bell },
    { pattern: /goal|target|objective/i, icon: Target },
];

function SubmenuIcon({ item, nested = false }: { item: NavItem; nested?: boolean }) {
    const identifier = `${item.title} ${item.href ?? ''}`;
    const Icon = item.icon
        ?? fallbackSubmenuIcons.find(({ pattern }) => pattern.test(identifier))?.icon
        ?? CircleDot;

    return (
        <Icon
            aria-hidden="true"
            className={nested
                ? 'h-3.5 w-3.5 shrink-0 text-current opacity-60'
                : 'h-4 w-4 shrink-0 text-current opacity-70'}
        />
    );
}

export function NavMain({ items = [], searchQuery = "" }: { items: NavItem[], searchQuery?: string }) {
    const page = usePage();

    // Filter items based on search query
    const filterItems = (items: NavItem[], query: string): NavItem[] => {
        if (!query) return items;

        return items.reduce((acc, item) => {
            const matchesTitle = item.title.toLowerCase().includes(query.toLowerCase());
            const filteredChildren = item.children ? filterItems(item.children, query) : [];

            if (matchesTitle || filteredChildren.length > 0) {
                acc.push({
                    ...item,
                    children: filteredChildren.length > 0 ? filteredChildren : item.children
                });
            }
            return acc;
        }, [] as NavItem[]);
    };

    const filteredItems = filterItems(items, searchQuery);

    // Helper function to check if URL matches (exact or starts with for detail pages)
    const isUrlActive = (itemPath: string): boolean => {
        const currentPath = page.url.split('?')[0];
        return currentPath === itemPath || currentPath.startsWith(itemPath + '/');
    };

    // Helper function to check if any child is active (recursive for nested children)
    const isChildActive = (children: NavItem[]): boolean => {
        return children.some(child => {
            if (child.href) {
                const childPath = new URL(child.href, window.location.origin).pathname;
                return isUrlActive(childPath);
            }
            if (child.children) {
                return isChildActive(child.children);
            }
            return false;
        });
    };

    return (
        <SidebarGroup className="px-1 py-2">
            <SidebarGroupLabel className="mb-1 h-7 px-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">
                {searchQuery ? 'Search results' : 'Workspace'}
            </SidebarGroupLabel>
            <SidebarMenu className="gap-1.5">
                {filteredItems.map((item) => {
                  const itemPath = item.href ? new URL(item.href, window.location.origin).pathname : '';
                  const isActive = !!(itemPath && isUrlActive(itemPath));

                  // Check if any child is active for parent menus
                  const hasActiveChild = item.children ? isChildActive(item.children) : false;
                  const shouldBeActive = isActive || hasActiveChild;
                    if (item.children && item.children.length > 0) {
                        return (
                            <SidebarMenuItem key={item.title}>
                                {/* Expanded sidebar - use collapsible */}
                                <Collapsible asChild defaultOpen={shouldBeActive} className="group/collapsible group-data-[collapsible=icon]:hidden">
                                    <div>
                                        <CollapsibleTrigger asChild>
                                            <SidebarMenuButton tooltip={item.title} isActive={shouldBeActive} className="h-10 rounded-xl px-3 text-[13px] font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-950 data-[active=true]:bg-primary/10 data-[active=true]:text-primary dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white">
                                                {item.icon && <item.icon />}
                                                <span>{item.title}</span>
                                                <ChevronDown className="ml-auto h-4 w-4 transition-transform group-data-[state=open]/collapsible:rotate-180" />
                                            </SidebarMenuButton>
                                        </CollapsibleTrigger>
                                        <CollapsibleContent>
                                            <SidebarMenuSub>
                                                {item.children.map((subItem) => {
                                                    const subItemActive = !!(subItem.href && isUrlActive(new URL(subItem.href, window.location.origin).pathname));
                                                    const hasActiveSubChild = subItem.children ? isChildActive(subItem.children) : false;
                                                    const subItemShouldBeActive = subItemActive || hasActiveSubChild;

                                                    if (subItem.children && subItem.children.length > 0) {
                                                        return (
                                                            <SidebarMenuSubItem key={subItem.title}>
                                                                <Collapsible asChild defaultOpen={subItemShouldBeActive} className="group/subcollapsible">
                                                                    <div>
                                                                        <CollapsibleTrigger asChild>
                                                                            <SidebarMenuSubButton isActive={subItemShouldBeActive} className="h-9 rounded-lg text-[13px]">
                                                                                <SubmenuIcon item={subItem} />
                                                                                <span>{subItem.title}</span>
                                                                                <ChevronDown className="ml-auto h-3 w-3 transition-transform group-data-[state=open]/subcollapsible:rotate-180" />
                                                                            </SidebarMenuSubButton>
                                                                        </CollapsibleTrigger>
                                                                        <CollapsibleContent>
                                                                            <SidebarMenuSub>
                                                                                {subItem.children.map((subSubItem) => (
                                                                                    <SidebarMenuSubItem key={subSubItem.title}>
                                                                                        <SidebarMenuSubButton
                                                                                            asChild
                                                                                            isActive={!!(subSubItem.href && isUrlActive(new URL(subSubItem.href, window.location.origin).pathname))}
                                                                                            className="h-8 rounded-lg text-[13px]"
                                                                                        >
                                                                                            <Link href={subSubItem.href!}>
                                                                                                <SubmenuIcon item={subSubItem} nested />
                                                                                                <span>{subSubItem.title}</span>
                                                                                            </Link>
                                                                                        </SidebarMenuSubButton>
                                                                                    </SidebarMenuSubItem>
                                                                                ))}
                                                                            </SidebarMenuSub>
                                                                        </CollapsibleContent>
                                                                    </div>
                                                                </Collapsible>
                                                            </SidebarMenuSubItem>
                                                        );
                                                    }

                                                    return (
                                                        <SidebarMenuSubItem key={subItem.title}>
                                                            <SidebarMenuSubButton
                                                                asChild
                                                                isActive={subItemActive}
                                                                className="h-9 rounded-lg text-[13px] text-slate-500 data-[active=true]:bg-primary/10 data-[active=true]:text-primary dark:text-slate-400"
                                                            >
                                                                <Link href={subItem.href!}>
                                                                    <SubmenuIcon item={subItem} />
                                                                    <span>{subItem.title}</span>
                                                                </Link>
                                                            </SidebarMenuSubButton>
                                                        </SidebarMenuSubItem>
                                                    );
                                                })}
                                            </SidebarMenuSub>
                                        </CollapsibleContent>
                                    </div>
                                </Collapsible>

                                {/* Collapsed sidebar - use dropdown */}
                                <div className="hidden group-data-[collapsible=icon]:block" onMouseEnter={(e) => e.stopPropagation()} onMouseLeave={(e) => e.stopPropagation()}>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <SidebarMenuButton
                                                tooltip={item.title}
                                                isActive={shouldBeActive}
                                                className="h-10 rounded-xl"
                                            >
                                                {item.icon && <item.icon />}
                                                <span>{item.title}</span>
                                            </SidebarMenuButton>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent side="right" align="start" className="w-48">
                                            {item.children.map((subItem) => {
                                                if (subItem.children && subItem.children.length > 0) {
                                                    return (
                                                        <DropdownMenu key={subItem.title}>
                                                            <DropdownMenuTrigger asChild>
                                                                <DropdownMenuItem className="flex items-center gap-2 cursor-pointer">
                                                                    <SubmenuIcon item={subItem} />
                                                                    <span>{subItem.title}</span>
                                                                    <ChevronDown className="ml-auto h-3 w-3" />
                                                                </DropdownMenuItem>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent side="right" align="start" className="w-44">
                                                                {subItem.children.map((subSubItem) => (
                                                                    <DropdownMenuItem key={subSubItem.title} asChild>
                                                                        <Link href={subSubItem.href!} className="flex items-center gap-2">
                                                                            <SubmenuIcon item={subSubItem} nested />
                                                                            <span className="text-sm">{subSubItem.title}</span>
                                                                        </Link>
                                                                    </DropdownMenuItem>
                                                                ))}
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                    );
                                                }

                                                return (
                                                    <DropdownMenuItem key={subItem.title} asChild>
                                                        <Link href={subItem.href!} className="flex items-center gap-2">
                                                            <SubmenuIcon item={subItem} />
                                                            <span>{subItem.title}</span>
                                                        </Link>
                                                    </DropdownMenuItem>
                                                );
                                            })}
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </SidebarMenuItem>
                        );
                    }

                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={shouldBeActive}
                                tooltip={item.title}
                                className="h-10 rounded-xl px-3 text-[13px] font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-950 data-[active=true]:bg-primary/10 data-[active=true]:text-primary dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white"
                            >
                                <Link href={item.href!}>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
