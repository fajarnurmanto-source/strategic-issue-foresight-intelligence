import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        // SIFI_SINGLE_APP_SHELL_V1: local page AppLayout takes precedence over the global default.
        // Auth and settings keep their existing global layout behavior.
        if (
            new Set([
                'dashboard',
                'sifi/claim-event-workspace',
                'sifi/e4-period-discovery',
                'sifi/evidence-diagnostics',
                'sifi/evidence-intake',
                'sifi/evidence-review-queue',
                'sifi/executive-brief-workspace',
                'sifi/foresight-workspace',
                'sifi/investigation-detail',
                'sifi/investigations-index',
                'sifi/issue-candidate-detail',
                'sifi/issue-candidates-index',
                'sifi/issue-case-workspace',
                'sifi/product-roadmap',
                'sifi/signal-inbox',
                'sifi/signal-relations',
                'sifi/source-catalog',
                'sifi/trajectory-workspace',
                'sifi/workbench',
            ]).has(name)
        ) {
            return null;
        }

        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
