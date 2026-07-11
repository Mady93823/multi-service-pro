import { useTrans } from '@/lib/i18n';
import { type SupportTicketMessage } from '@/types';
import { Paperclip } from 'lucide-react';

interface TicketThreadProps {
    messages: SupportTicketMessage[];
    /** Which side of the thread reads as "own" (right-aligned). */
    viewerIsStaff: boolean;
}

export function TicketThread({ messages, viewerIsStaff }: TicketThreadProps) {
    const t = useTrans();

    const dateFormat = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });

    return (
        <div className="flex flex-col gap-3">
            {messages.map((message) => {
                const own = message.is_staff === viewerIsStaff;

                return (
                    <div key={message.id} className={`flex ${own ? 'justify-end' : 'justify-start'}`}>
                        <div className={`max-w-[85%] rounded-lg border p-3 text-sm ${own ? 'bg-primary/10' : 'bg-muted/40'}`}>
                            <p className="text-muted-foreground mb-1 text-xs">
                                <span className="text-foreground font-medium">
                                    {message.is_staff ? t('Support team') : (message.author_name ?? t('Customer'))}
                                </span>
                                {message.created_at !== null && <> · {dateFormat.format(new Date(message.created_at))}</>}
                            </p>
                            <p className="whitespace-pre-wrap">{message.body}</p>
                            {message.attachments.length > 0 && (
                                <ul className="mt-2 flex flex-col gap-1">
                                    {message.attachments.map((attachment) => (
                                        <li key={attachment.id}>
                                            <a
                                                href={attachment.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="text-primary inline-flex items-center gap-1 text-xs hover:underline"
                                            >
                                                <Paperclip className="h-3 w-3" />
                                                {attachment.name}
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
