import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface SocialValues {
    facebook: string | null;
    instagram: string | null;
    x: string | null;
    youtube: string | null;
    linkedin: string | null;
    whatsapp: string | null;
}

const NETWORKS: { key: keyof SocialValues; label: string }[] = [
    { key: 'facebook', label: 'Facebook' },
    { key: 'instagram', label: 'Instagram' },
    { key: 'x', label: 'X' },
    { key: 'youtube', label: 'YouTube' },
    { key: 'linkedin', label: 'LinkedIn' },
    { key: 'whatsapp', label: 'WhatsApp' },
];

export default function SocialForm({ values }: { values: SocialValues }) {
    const t = useTrans();

    const { data, setData, put, processing, errors } = useForm({
        facebook: values.facebook ?? '',
        instagram: values.instagram ?? '',
        x: values.x ?? '',
        youtube: values.youtube ?? '',
        linkedin: values.linkedin ?? '',
        whatsapp: values.whatsapp ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'social'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            {NETWORKS.map((network) => (
                <div key={network.key} className="grid gap-2">
                    <Label htmlFor={network.key}>{network.label}</Label>
                    <Input
                        id={network.key}
                        type="url"
                        value={data[network.key]}
                        onChange={(e) => setData(network.key, e.target.value)}
                        placeholder="https://"
                    />
                    <InputError message={errors[network.key]} />
                </div>
            ))}

            <p className="text-muted-foreground text-xs">{t('Blank networks are hidden from the footer.')}</p>

            <SaveButton processing={processing} />
        </form>
    );
}
