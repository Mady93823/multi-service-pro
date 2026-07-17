import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/lib/i18n';
import { router, useForm, usePage } from '@inertiajs/react';
import { PlugZap } from 'lucide-react';
import { FormEventHandler } from 'react';

export interface StorageValues {
    driver: string;
    s3_key: string;
    s3_bucket: string;
    s3_region: string;
    s3_endpoint: string;
    s3_url: string;
    s3_path_style: boolean;
    s3_secret_set: boolean;
}

type StorageForm = {
    driver: string;
    s3_key: string;
    s3_bucket: string;
    s3_region: string;
    s3_endpoint: string;
    s3_url: string;
    s3_path_style: boolean;
    s3_secret: string;
    remove_s3_secret: boolean;
};

/**
 * Where uploaded media lives (D40): this server, or an S3-compatible bucket
 * such as Cloudflare R2. Switching affects new uploads only — every file
 * keeps serving from the disk it was written to.
 */
export default function StorageForm({ values }: { values: StorageValues }) {
    const t = useTrans();
    const pageErrors = usePage().props.errors as Record<string, string | undefined>;

    const { data, setData, put, processing, errors } = useForm<StorageForm>({
        driver: values.driver,
        s3_key: values.s3_key,
        s3_bucket: values.s3_bucket,
        s3_region: values.s3_region,
        s3_endpoint: values.s3_endpoint,
        s3_url: values.s3_url,
        s3_path_style: values.s3_path_style,
        // Write-only: blank means "keep the stored secret".
        s3_secret: '',
        remove_s3_secret: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'storage'), { preserveScroll: true });
    };

    // Probes the STORED settings — save first, then test.
    const testConnection = () => router.post(route('admin.settings.storage.test'), {}, { preserveScroll: true });

    const s3 = data.driver === 's3';

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label>{t('Active file storage')}</Label>
                <div className="flex flex-wrap gap-4">
                    {[
                        { value: 'local', label: t('This server') },
                        { value: 's3', label: t('Cloudflare R2 / S3 bucket') },
                    ].map((option) => (
                        <label key={option.value} className="flex items-center gap-2 text-sm">
                            <input
                                type="radio"
                                name="driver"
                                value={option.value}
                                checked={data.driver === option.value}
                                onChange={() => setData('driver', option.value)}
                            />
                            {option.label}
                        </label>
                    ))}
                </div>
                <InputError message={errors.driver} />
                <p className="text-muted-foreground text-xs">
                    {t('Only new uploads move. Files already on this server keep working, and private documents never leave it.')}
                </p>
            </div>

            {s3 && (
                <div className="space-y-4 rounded-lg border p-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="s3_key">{t('Access key ID')}</Label>
                            <Input id="s3_key" value={data.s3_key} onChange={(e) => setData('s3_key', e.target.value)} autoComplete="off" />
                            <InputError message={errors.s3_key} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="s3_secret">{t('Secret access key')}</Label>
                            <Input
                                id="s3_secret"
                                type="password"
                                autoComplete="off"
                                value={data.s3_secret}
                                disabled={data.remove_s3_secret}
                                onChange={(e) => setData('s3_secret', e.target.value)}
                                placeholder={values.s3_secret_set ? t('Saved — leave blank to keep it') : t('Not set')}
                            />
                            <InputError message={errors.s3_secret} />
                            {values.s3_secret_set && (
                                <label className="text-muted-foreground flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={data.remove_s3_secret}
                                        onCheckedChange={(checked) => setData('remove_s3_secret', checked === true)}
                                    />
                                    {t('Remove this secret')}
                                </label>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="s3_bucket">{t('Bucket')}</Label>
                            <Input id="s3_bucket" value={data.s3_bucket} onChange={(e) => setData('s3_bucket', e.target.value)} autoComplete="off" />
                            <InputError message={errors.s3_bucket} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="s3_region">{t('Region')}</Label>
                            <Input id="s3_region" value={data.s3_region} onChange={(e) => setData('s3_region', e.target.value)} placeholder="auto" />
                            <InputError message={errors.s3_region} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="s3_endpoint">{t('S3 endpoint')}</Label>
                        <Input
                            id="s3_endpoint"
                            value={data.s3_endpoint}
                            onChange={(e) => setData('s3_endpoint', e.target.value)}
                            placeholder="https://<account-id>.r2.cloudflarestorage.com"
                            autoComplete="off"
                        />
                        <InputError message={errors.s3_endpoint} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="s3_url">{t('Public / custom URL')}</Label>
                        <Input
                            id="s3_url"
                            value={data.s3_url}
                            onChange={(e) => setData('s3_url', e.target.value)}
                            placeholder="https://pub-xxxxxxxx.r2.dev"
                            autoComplete="off"
                        />
                        <InputError message={errors.s3_url} />
                        <p className="text-muted-foreground text-xs">
                            {t('The address browsers load images from — an R2 public bucket URL or your CDN domain.')}
                        </p>
                    </div>

                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={data.s3_path_style} onCheckedChange={(checked) => setData('s3_path_style', checked === true)} />
                        {t('Use path-style addressing (MinIO and some S3 clones)')}
                    </label>

                    <div className="space-y-2">
                        <Button type="button" variant="outline" size="sm" onClick={testConnection}>
                            <PlugZap className="h-4 w-4" />
                            {t('Test connection')}
                        </Button>
                        <InputError message={pageErrors.storage} />
                        <p className="text-muted-foreground text-xs">{t('Runs against the saved settings — save your changes first.')}</p>
                    </div>
                </div>
            )}

            <SaveButton processing={processing} />
        </form>
    );
}
