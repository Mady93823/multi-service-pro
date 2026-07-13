import AppLogoIcon from '@/components/app-logo-icon';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    const { name, site } = usePage<SharedData>().props;
    const { login_headline: headline, login_subcopy: subcopy, login_image_url: image } = site.appearance;

    // The side panel is admin-owned copy (M19). An install that sets none of it
    // gets the plain centered card this layout has always been.
    const hasPanel = headline !== null || subcopy !== null || image !== null;

    const form = (
        <div className="w-full max-w-sm">
            <div className="flex flex-col gap-8">
                <div className="flex flex-col items-center gap-4">
                    <Link href={route('home')} className="flex flex-col items-center gap-2 font-medium">
                        <div className="mb-1 flex h-9 w-9 items-center justify-center rounded-md">
                            <AppLogoIcon className="size-9 fill-current text-[var(--foreground)] dark:text-white" />
                        </div>
                        <span className="sr-only">{title}</span>
                    </Link>

                    <div className="space-y-2 text-center">
                        <h1 className="text-xl font-medium">{title}</h1>
                        <p className="text-muted-foreground text-center text-sm">{description}</p>
                    </div>
                </div>
                {children}
            </div>
        </div>
    );

    if (!hasPanel) {
        return <div className="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">{form}</div>;
    }

    return (
        <div className="bg-background grid min-h-svh lg:grid-cols-2">
            <div className="relative hidden flex-col justify-end overflow-hidden p-10 lg:flex">
                {image !== null ? (
                    <>
                        <img src={image} alt="" className="absolute inset-0 h-full w-full object-cover" />
                        <div className="absolute inset-0 bg-gradient-to-t from-black/70 to-black/10" />
                    </>
                ) : (
                    <div className="from-primary/20 to-primary/5 absolute inset-0 bg-gradient-to-br" />
                )}

                <div className={`relative space-y-3 ${image !== null ? 'text-white' : ''}`}>
                    {headline !== null && <h2 className="text-3xl font-semibold">{headline}</h2>}
                    {subcopy !== null && <p className={image !== null ? 'text-white/80' : 'text-muted-foreground'}>{subcopy}</p>}
                    {headline === null && subcopy === null && <p className="text-muted-foreground text-lg font-medium">{name}</p>}
                </div>
            </div>

            <div className="flex items-center justify-center p-6 md:p-10">{form}</div>
        </div>
    );
}
