import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import CustomerLayout from '@/layouts/customer-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import { Radio } from 'lucide-react';
import { toast } from 'sonner';

type DemoPingPayload = {
    message: string;
    sent_at: string;
};

export default function CustomerDashboard() {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('Dashboard'),
            href: '/dashboard',
        },
    ];

    // Phase 1 WebSocket smoke test; replaced by real tracking events in Phase 3.
    useEchoPublic<DemoPingPayload>('demo', 'DemoPing', (payload) => {
        toast.success(payload.message, {
            description: new Date(payload.sent_at).toLocaleTimeString(),
        });
    });

    const sendPing = () => {
        router.post(route('demo.ping'), {}, { preserveScroll: true });
    };

    return (
        <CustomerLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Dashboard')} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card className="max-w-xl">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Radio className="h-5 w-5" />
                            {t('Realtime connection check')}
                        </CardTitle>
                        <CardDescription>
                            {t('Sends a test event through the WebSocket server. Every open browser tab on this page shows a toast when it arrives.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button onClick={sendPing}>{t('Send test broadcast')}</Button>
                    </CardContent>
                </Card>
            </div>
        </CustomerLayout>
    );
}
