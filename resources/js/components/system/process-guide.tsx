import { Button } from '@/components/ui/button';
import { useTrans } from '@/lib/i18n';
import { Check, Copy } from 'lucide-react';
import { useState } from 'react';

export type Deployment = {
    cron: string;
    supervisor: string;
    systemd_queue: string;
    systemd_reverb: string;
    php: string;
    root: string;
};

function CopyBlock({ label, hint, code }: { label: string; hint: string; code: string }) {
    const t = useTrans();
    const [copied, setCopied] = useState(false);

    const copy = () => {
        void navigator.clipboard.writeText(code).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    };

    return (
        <div className="space-y-1.5">
            <div className="flex items-center justify-between gap-2">
                <div>
                    <p className="text-sm font-medium">{label}</p>
                    <p className="text-muted-foreground text-xs">{hint}</p>
                </div>
                <Button type="button" variant="outline" size="sm" onClick={copy}>
                    {copied ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
                    {copied ? t('Copied') : t('Copy')}
                </Button>
            </div>
            <pre className="bg-muted overflow-x-auto rounded-md p-3 text-xs leading-relaxed">
                <code>{code}</code>
            </pre>
        </div>
    );
}

/**
 * The three processes an install needs, as copy-paste text with this install's
 * own paths already in them.
 *
 * All three fail silently when they are missing — every page still loads. No
 * cron: payouts never release and unpaid bookings never expire. No queue worker:
 * every notification is written and none is sent. No Reverb: the live map never
 * moves. "Did you set up the worker?" is the support ticket this replaces.
 */
export function ProcessGuide({ deployment }: { deployment: Deployment }) {
    const t = useTrans();

    return (
        <div className="space-y-5">
            <CopyBlock
                label={t('1. Task scheduler (cron)')}
                hint={t('Without it payouts never release and unpaid bookings never expire.')}
                code={deployment.cron}
            />
            <CopyBlock
                label={t('2. Queue worker and WebSocket server (Supervisor)')}
                hint={t('Without them notifications are never sent and the live map never moves.')}
                code={deployment.supervisor}
            />
            <CopyBlock
                label={t('Queue worker (systemd alternative)')}
                hint={t('Use this instead of Supervisor: /etc/systemd/system/urbanserve-queue.service')}
                code={deployment.systemd_queue}
            />
            <CopyBlock
                label={t('WebSocket server (systemd alternative)')}
                hint={t('Use this instead of Supervisor: /etc/systemd/system/urbanserve-reverb.service')}
                code={deployment.systemd_reverb}
            />
            <p className="text-muted-foreground text-xs">
                {t('Point your web server at the public/ directory, and proxy /app and /apps to port 8080 for WebSockets.')}
            </p>
        </div>
    );
}
