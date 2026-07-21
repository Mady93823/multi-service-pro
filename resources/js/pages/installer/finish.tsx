import { ProcessGuide, type Deployment } from '@/components/system/process-guide';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import InstallerLayout from '@/layouts/installer-layout';
import { useTrans } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';

export default function Finish({ deployment }: { deployment: Deployment }) {
    const t = useTrans();

    return (
        <InstallerLayout step={4}>
            <Head title={t('Install — Finished')} />
            <div className="space-y-4">
                <Card>
                    <CardHeader className="items-center text-center">
                        <CheckCircle2 className="mx-auto h-10 w-10 text-green-600" />
                        <CardTitle>{t('Installation complete')}</CardTitle>
                        <CardDescription>{t('Sign in with the administrator account you just created.')}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <Button asChild className="w-full">
                            <Link href="/login">{t('Go to login')}</Link>
                        </Button>
                        <Button asChild variant="outline" className="w-full">
                            <a href="/">{t('Go to home page')}</a>
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{t('Three processes to start')}</CardTitle>
                        <CardDescription>
                            {t('The site works without them — and quietly does less. You can find this again under System in the admin panel.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ProcessGuide deployment={deployment} />
                    </CardContent>
                </Card>
            </div>
        </InstallerLayout>
    );
}
