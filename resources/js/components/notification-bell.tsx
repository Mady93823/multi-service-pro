import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useTrans } from '@/lib/i18n';
import { type AppNotification, type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';

function NotificationRow({ notification }: { notification: AppNotification }) {
    const unread = notification.read_at === null;

    const open = () => {
        router.post(route('notifications.read', notification.id), {}, { preserveScroll: true, preserveState: true, only: ['notifications'] });
        if (notification.url) {
            router.visit(notification.url);
        }
    };

    return (
        <button type="button" onClick={open} className="hover:bg-muted flex w-full items-start gap-2 rounded-lg px-2 py-2 text-left text-sm">
            <span className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${unread ? 'bg-primary' : 'bg-transparent'}`} />
            <span className="min-w-0">
                <span className="block font-medium">{notification.title}</span>
                <span className="text-muted-foreground block truncate">{notification.body}</span>
            </span>
        </button>
    );
}

export function NotificationBell() {
    const t = useTrans();
    const { notifications } = usePage<SharedData>().props;
    const unread = notifications.unread_count;
    const recent = notifications.recent;

    const markAllRead = () => {
        router.post(route('notifications.read-all'), {}, { preserveScroll: true, preserveState: true, only: ['notifications'] });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative size-10 rounded-full" aria-label={t('Notifications')}>
                    <Bell className="h-5 w-5" />
                    {unread > 0 && (
                        <span className="bg-primary text-primary-foreground absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-semibold">
                            {unread > 9 ? '9+' : unread}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80 p-2">
                <div className="flex items-center justify-between px-2 py-1">
                    <span className="text-sm font-semibold">{t('Notifications')}</span>
                    {unread > 0 && (
                        <button type="button" onClick={markAllRead} className="text-primary text-xs hover:underline">
                            {t('Mark all read')}
                        </button>
                    )}
                </div>
                <div className="max-h-80 space-y-0.5 overflow-y-auto">
                    {recent.length === 0 ? (
                        <p className="text-muted-foreground px-2 py-6 text-center text-sm">{t('No notifications yet.')}</p>
                    ) : (
                        recent.map((notification) => <NotificationRow key={notification.id} notification={notification} />)
                    )}
                </div>
                <div className="border-t pt-1">
                    <Link href={route('notifications.index')} className="text-primary block px-2 py-1.5 text-center text-sm hover:underline">
                        {t('View all')}
                    </Link>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
