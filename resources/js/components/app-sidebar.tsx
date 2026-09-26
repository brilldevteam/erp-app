"use client"

import * as React from "react"
import {
  Search,
} from "lucide-react"

import { NavMain } from "@/components/nav-main"
import { NavUser } from "@/components/nav-user"
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarInput,
} from "@/components/ui/sidebar"
import {Link, usePage} from "@inertiajs/react";
import {PageProps} from "@/types";
import { allMenuItems } from "@/utils/menu";
import { useBrand } from "@/contexts/brand-context";



export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
    const { auth } = usePage<PageProps>().props;
    const { settings, getCompleteSidebarProps, getLogoSrc } = useBrand();
    const [searchQuery, setSearchQuery] = React.useState("");
    const scrollRef = React.useRef<HTMLDivElement>(null);

    // Preserve scroll position on navigation
    React.useEffect(() => {
        const savedScroll = sessionStorage.getItem('sidebar-scroll');
        if (savedScroll && scrollRef.current) {
            scrollRef.current.scrollTop = parseInt(savedScroll);
        }
    }, []);

    const handleScroll = () => {
        if (scrollRef.current) {
            sessionStorage.setItem('sidebar-scroll', scrollRef.current.scrollTop.toString());
        }
    };

    const sidebarProps = getCompleteSidebarProps();

    return (
    <Sidebar
        variant={settings.sidebarVariant as any}
        side={settings.layoutDirection === 'rtl' ? 'right' : 'left'}
        collapsible="icon"
        className={`${sidebarProps.className || ''} modern-app-sidebar border-r border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-950`}
        style={sidebarProps.style}
        {...props}
    >
      <SidebarHeader className="gap-5 px-4 pb-4 pt-5 group-data-[collapsible=icon]:px-2">
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg" asChild className="h-12 rounded-xl hover:bg-slate-50 group-data-[collapsible=icon]:justify-center dark:hover:bg-slate-900">
              <Link href={route('dashboard')} className="flex items-center justify-start px-1">
                {/* Logo for expanded sidebar */}
                <div className="group-data-[collapsible=icon]:hidden flex items-center">
                  {(() => {
                    const { getPreviewUrl } = useBrand();
                    const displayUrl = getLogoSrc() ? getPreviewUrl(getLogoSrc()) : '';

                    return displayUrl ? (
                      <img
                        src={displayUrl}
                        alt="Logo"
                        className="w-auto max-w-32 transition-all duration-200"
                      />
                    ) : (
                      <div className="flex items-center text-xl font-bold tracking-[-0.045em] text-slate-950 dark:text-white">
                        {settings.titleText || 'Wazely ERP'}
                      </div>
                    );
                  })()}
                </div>

                {/* Icon for collapsed sidebar */}
                <div className="h-8 w-8 hidden group-data-[collapsible=icon]:block">
                  {(() => {
                    const { getPreviewUrl } = useBrand();
                    const displayFavicon = settings.favicon ? getPreviewUrl(settings.favicon) : '';

                    return displayFavicon ? (
                      <img
                        src={displayFavicon}
                        alt="Icon"
                        className="h-8 w-8 transition-all duration-200"
                      />
                    ) : (
                      <div className="h-8 w-8 bg-primary text-white rounded flex items-center justify-center font-bold shadow-sm">
                        W
                      </div>
                    );
                  })()}
                </div>
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
        <div className="px-2 group-data-[collapsible=icon]:px-2">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 group-data-[collapsible=icon]:hidden" />
            <SidebarInput
              placeholder="Search menu..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="h-10 rounded-xl border-slate-200 bg-slate-50/80 pl-9 text-sm shadow-none placeholder:text-slate-400 focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/10 group-data-[collapsible=icon]:hidden dark:border-slate-800 dark:bg-slate-900/70"
            />
          </div>
        </div>
      </SidebarHeader>
      <SidebarContent ref={scrollRef} onScroll={handleScroll} className="px-2 pb-4">
        <NavMain items={allMenuItems()} searchQuery={searchQuery} />
      </SidebarContent>
      <SidebarFooter className="border-t border-slate-200/80 px-3 py-3 group-data-[collapsible=icon]:px-2 dark:border-slate-800">
        <NavUser user={auth.user} />
      </SidebarFooter>
    </Sidebar>
  )
}
