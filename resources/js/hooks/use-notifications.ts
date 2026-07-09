import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useEchoNotification } from '@laravel/echo-react';
import { toast } from 'sonner';

interface IncomingNotification {
    title?: string;
    body?: string;
    url?: string | null;
}

/**
 * Live in-app notifications (M11). Subscribes to the user's private channel,
 * toasts each incoming notification, and refreshes the shared `notifications`
 * prop so the bell badge updates without a full navigation. Call once per
 * authenticated layout.
 */
export function useNotifications() {
    const { auth } = usePage<SharedData>().props;
    const userId = auth.user.id;

    useEchoNotification<IncomingNotification>(`App.Models.User.${userId}`, (notification) => {
        if (notification.title) {
            toast(notification.title, { description: notification.body });
        }
        router.reload({ only: ['notifications'] });
    });
}
