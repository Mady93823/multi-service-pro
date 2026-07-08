import { useBookingStatusLabels } from '@/components/booking/status-badge';
import { useTrans } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { type BookingHistoryEntry } from '@/types';

/**
 * Vertical audit trail from booking_status_history. Same-status entries are
 * annotations (e.g. reschedules); their note carries the story.
 */
export function BookingTimeline({ history }: { history: BookingHistoryEntry[] }) {
    const t = useTrans();
    const labels = useBookingStatusLabels();

    const actorLabels: Record<BookingHistoryEntry['actor_type'], string> = {
        customer: t('Customer'),
        provider: t('Professional'),
        admin: t('Support team'),
        system: t('System'),
    };

    if (history.length === 0) {
        return null;
    }

    return (
        <ol className="space-y-0">
            {history.map((entry, index) => (
                <li key={entry.id} className="relative flex gap-3 pb-6 last:pb-0">
                    <div className="flex flex-col items-center">
                        <span
                            className={cn(
                                'mt-1 h-2.5 w-2.5 shrink-0 rounded-full',
                                index === history.length - 1 ? 'bg-primary' : 'bg-muted-foreground/40',
                            )}
                        />
                        {index < history.length - 1 && <span className="bg-border w-px flex-1" />}
                    </div>
                    <div className="min-w-0 text-sm">
                        <p className="font-medium">
                            {entry.from_status === entry.to_status && entry.note !== null ? entry.note : labels[entry.to_status]}
                        </p>
                        <p className="text-muted-foreground text-xs">
                            {entry.created_label} · {actorLabels[entry.actor_type]}
                        </p>
                        {entry.from_status !== entry.to_status && entry.note !== null && (
                            <p className="text-muted-foreground mt-1 text-xs">{entry.note}</p>
                        )}
                    </div>
                </li>
            ))}
        </ol>
    );
}
