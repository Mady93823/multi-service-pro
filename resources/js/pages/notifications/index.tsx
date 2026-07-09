import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AdminLayout from '@/layouts/admin-layout';
import CustomerLayout from '@/layouts/customer-layout';
import ProviderLayout from '@/layouts/provider-layout';
import { useTrans } from '@/lib/i18n';
import { type AppNotification, type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCheck } from 'lucide-react';
import { type ComponentType } from 'react';

interface NativePaginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface NotificationsIndexProps {
    entries: NativePaginated<AppNotification>;
}

interface LayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

function layoutForRoles(roles: string[]): ComponentType<LayoutProps> {
    if (roles.includes('admin')) {
        return AdminLayout;
    }
    if (roles.includes('provider')) {
        return ProviderLayout;
    }

    return CustomerLayout;
}

export default function NotificationsIndex({ entries }: NotificationsIndexProps) {
    const t = useTrans();
    const { auth } = usePage<SharedData>().props;
    const Layout = layoutForRoles(auth.roles);

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Notifications'), href: '/notifications' }];

    const open = (notification: AppNotification) => {
        router.post(
            route('notifications.read', notification.id),
            {},
            { preserveScroll: true, preserveState: true, only: ['notifications', 'entries'] },
        );
        if (notification.url) {
            router.visit(notification.url);
        }
    };

    const markAllRead = () => {
        router.post(route('notifications.read-all'), {}, { preserveScroll: true });
    };

    return (
        <Layout breadcrumbs={breadcrumbs}>
            <Head title={t('Notifications')} />
            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{t('Notifications')}</h1>
                    <Button variant="outline" size="sm" onClick={markAllRead}>
                        <CheckCheck className="h-4 w-4" />
                        {t('Mark all read')}
                    </Button>
                </div>

                <Card>
                    <CardContent className="divide-y p-0">
                        {entries.data.length === 0 && <p className="text-muted-foreground p-6 text-center text-sm">{t('No notifications yet.')}</p>}
                        {entries.data.map((notification) => (
                            <button
                                key={notification.id}
                                type="button"
                                onClick={() => open(notification)}
                                className="hover:bg-muted flex w-full items-start gap-3 p-4 text-left"
                            >
                                <span
                                    className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${notification.read_at === null ? 'bg-primary' : 'bg-transparent'}`}
                                />
                                <span className="min-w-0">
                                    <span className="block text-sm font-medium">{notification.title}</span>
                                    <span className="text-muted-foreground block text-sm">{notification.body}</span>
                                </span>
                            </button>
                        ))}
                    </CardContent>
                </Card>

                {(entries.prev_page_url !== null || entries.next_page_url !== null) && (
                    <div className="flex items-center justify-between text-sm">
                        {entries.prev_page_url !== null ? (
                            <Link href={entries.prev_page_url} preserveScroll className="text-primary hover:underline">
                                {t('Previous')}
                            </Link>
                        ) : (
                            <span />
                        )}
                        {entries.next_page_url !== null && (
                            <Link href={entries.next_page_url} preserveScroll className="text-primary hover:underline">
                                {t('Next')}
                            </Link>
                        )}
                    </div>
                )}
            </div>
        </Layout>
    );
}
