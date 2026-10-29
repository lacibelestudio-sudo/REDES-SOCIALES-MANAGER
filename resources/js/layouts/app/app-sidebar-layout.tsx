import { usePage } from '@inertiajs/react';

import { AppContent } from '@/components/layout/app-content';
import { AppShell } from '@/components/layout/app-shell';
import { AppSidebar } from '@/components/layout/app-sidebar';
import { AppSidebarHeader } from '@/components/layout/app-sidebar-header';
import { CommandPalette } from '@/components/layout/command-palette';
import type { AppLayoutProps } from '@/types';

import { isAnalyticsPrintRoute } from './is-analytics-print-route';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const isPrintReport = isAnalyticsPrintRoute(usePage().url);

    return (
        <AppShell variant="sidebar">
            <AppSidebar
                wrapperClassName={isPrintReport ? 'print:hidden' : undefined}
            />
            <AppContent
                variant="sidebar"
                className={
                    isPrintReport
                        ? 'print:m-0 print:ml-0 print:w-full print:max-w-none print:overflow-visible print:rounded-none print:shadow-none'
                        : 'overflow-x-hidden'
                }
            >
                <AppSidebarHeader
                    breadcrumbs={breadcrumbs}
                    className={isPrintReport ? 'print:hidden' : undefined}
                />
                {children}
            </AppContent>
            <CommandPalette />
        </AppShell>
    );
}
