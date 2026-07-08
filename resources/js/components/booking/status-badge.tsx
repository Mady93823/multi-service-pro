import { Badge } from '@/components/ui/badge';
import { useTrans } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { type BookingStatus } from '@/types';

export function useBookingStatusLabels(): Record<BookingStatus, string> {
    const t = useTrans();

    return {
        pending_payment: t('Pending payment'),
        placed: t('Placed'),
        searching: t('Finding a professional'),
        assigned: t('Professional assigned'),
        accepted: t('Accepted'),
        en_route: t('On the way'),
        arrived: t('Arrived'),
        in_progress: t('Job in progress'),
        completed: t('Completed'),
        cancelled_customer: t('Cancelled by customer'),
        cancelled_provider: t('Cancelled by professional'),
        cancelled_admin: t('Cancelled by support'),
        expired: t('Expired'),
        failed_payment: t('Payment failed'),
    };
}

const statusStyles: Record<BookingStatus, string> = {
    pending_payment: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    placed: 'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200',
    searching: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    assigned: 'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200',
    accepted: 'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200',
    en_route: 'bg-indigo-100 text-indigo-900 dark:bg-indigo-950 dark:text-indigo-200',
    arrived: 'bg-indigo-100 text-indigo-900 dark:bg-indigo-950 dark:text-indigo-200',
    in_progress: 'bg-violet-100 text-violet-900 dark:bg-violet-950 dark:text-violet-200',
    completed: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    cancelled_customer: 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200',
    cancelled_provider: 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200',
    cancelled_admin: 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200',
    expired: 'bg-muted text-muted-foreground',
    failed_payment: 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200',
};

export function BookingStatusBadge({ status, className }: { status: BookingStatus; className?: string }) {
    const labels = useBookingStatusLabels();

    return <Badge className={cn('border-transparent', statusStyles[status], className)}>{labels[status]}</Badge>;
}
