import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { FormEventHandler } from 'react';

export interface CustomCodeValues {
    enabled: boolean;
    css: string | null;
    js: string | null;
}

export default function CustomCodeForm({ values }: { values: CustomCodeValues }) {
    const t = useTrans();

    const { data, setData, put, processing, errors } = useForm({
        enabled: values.enabled,
        css: values.css ?? '',
        js: values.js ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'custom_code'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="border-destructive/40 bg-destructive/5 text-muted-foreground flex gap-3 rounded-lg border p-3 text-sm">
                <AlertTriangle className="text-destructive mt-0.5 h-4 w-4 shrink-0" />
                <p>
                    {t(
                        'Snippets run on every storefront page for every visitor. They are never loaded in the admin or provider panel, so a broken snippet can always be removed from here. Every save is recorded in the activity log.',
                    )}
                </p>
            </div>

            <label className="flex items-center justify-between gap-4 text-sm">
                <span>
                    <span className="font-medium">{t('Inject custom code')}</span>
                    <span className="text-muted-foreground block">{t('Off by default. Nothing is injected while this is off.')}</span>
                </span>
                <Switch checked={data.enabled} onCheckedChange={(checked) => setData('enabled', checked)} />
            </label>

            <div className="grid gap-2">
                <Label htmlFor="css">{t('Custom CSS')}</Label>
                <Textarea
                    id="css"
                    value={data.css}
                    onChange={(e) => setData('css', e.target.value)}
                    rows={8}
                    spellCheck={false}
                    className="font-mono text-xs"
                />
                <InputError message={errors.css} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="js">{t('Custom JavaScript')}</Label>
                <Textarea
                    id="js"
                    value={data.js}
                    onChange={(e) => setData('js', e.target.value)}
                    rows={8}
                    spellCheck={false}
                    className="font-mono text-xs"
                />
                <InputError message={errors.js} />
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}
