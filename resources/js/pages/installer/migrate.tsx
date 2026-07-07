import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import InstallerLayout from '@/layouts/installer-layout';
import { useTrans } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

interface MigrateProps {
    database: string;
}

export default function Migrate({ database }: MigrateProps) {
    const t = useTrans();
    const { data, setData, post, processing, errors } = useForm<{ demo: boolean }>({
        demo: true,
    });
    // server-side step errors arrive under keys outside the form fields
    const stepErrors = errors as Record<string, string>;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/install/migrate');
    };

    return (
        <InstallerLayout step={2}>
            <Head title={t('Install — Migrate')} />
            <Card>
                <CardHeader>
                    <CardTitle>{t('Set up the database')}</CardTitle>
                    <CardDescription>
                        {t('Tables and default data will be created in :database. This can take up to a minute.', { database })}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="space-y-4">
                        <label className="flex items-start gap-2 text-sm">
                            <Checkbox checked={data.demo} onCheckedChange={(checked) => setData('demo', checked === true)} className="mt-0.5" />
                            <span>
                                {t('Install demo content')}
                                <span className="text-muted-foreground block">
                                    {t('Sample categories, services and demo accounts — recommended for evaluation.')}
                                </span>
                            </span>
                        </label>
                        <InputError message={stepErrors.migrate} />

                        <Button type="submit" className="w-full" disabled={processing}>
                            {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            {processing ? t('Running migrations...') : t('Run migrations & seed')}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </InstallerLayout>
    );
}
