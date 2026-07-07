import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Brand mark driven by the settings registry (M14, D8 white-label):
 * uploaded logo when configured, otherwise the app name's initial.
 */
export default function AppLogoIcon({ className }: { className?: string }) {
    const { name, branding } = usePage<SharedData>().props;

    if (branding.logo_url) {
        return <img src={branding.logo_url} alt={name} className={cn('object-contain', className)} />;
    }

    return (
        <span aria-hidden className={cn('flex items-center justify-center text-lg leading-none font-bold', className)}>
            {name.charAt(0).toUpperCase()}
        </span>
    );
}
