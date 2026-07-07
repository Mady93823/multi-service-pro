import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import InstallerLayout from '@/layouts/installer-layout';
import { useTrans } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';

export default function Finish() {
    const t = useTrans();

    return (
        <InstallerLayout step={4}>
            <Head title={t('Install — Finished')} />
            <Card>
                <CardHeader className="items-center text-center">
                    <CheckCircle2 className="mx-auto h-10 w-10 text-green-600" />
                    <CardTitle>{t('Installation complete')}</CardTitle>
                    <CardDescription>{t('Sign in with the administrator account you just created.')}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-3 text-center">
                    <Button asChild className="w-full">
                        <Link href="/login">{t('Go to login')}</Link>
                    </Button>
                    <p className="text-muted-foreground text-xs">
                        {t('For realtime features, keep the WebSocket and queue processes running — see the deployment guide.')}
                    </p>
                </CardContent>
            </Card>
        </InstallerLayout>
    );
}
