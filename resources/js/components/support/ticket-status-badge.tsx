import { Badge } from '@/components/ui/badge';
import { type SupportTicket } from '@/types';

const styles: Record<SupportTicket['status'], string> = {
    open: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    resolved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    closed: 'bg-muted text-muted-foreground',
};

export function TicketStatusBadge({ ticket }: { ticket: SupportTicket }) {
    return (
        <Badge variant="outline" className={`border-transparent ${styles[ticket.status]}`}>
            {ticket.status_label}
        </Badge>
    );
}
