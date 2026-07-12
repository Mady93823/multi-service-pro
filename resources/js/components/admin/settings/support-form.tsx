import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/lib/i18n';
import { type CannedResponse } from '@/types';
import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { FormEventHandler } from 'react';

export interface SupportValues {
    support_max_attachments: number;
    support_canned_responses: CannedResponse[];
}

/**
 * Inertia's useForm needs an implicit index signature, which an `interface`
 * never has — the canned responses are typed as an alias here even though the
 * prop side reuses the shared CannedResponse interface (M05 gotcha).
 */
type CannedResponseInput = { title: string; body: string };

type SupportForm = {
    support_max_attachments: number;
    support_canned_responses: CannedResponseInput[];
};

export default function SupportForm({ values }: { values: SupportValues }) {
    const t = useTrans();

    const { data, setData, put, processing, errors } = useForm<SupportForm>({
        support_max_attachments: values.support_max_attachments,
        support_canned_responses: values.support_canned_responses,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'support'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="support_max_attachments">{t('Attachments per message')}</Label>
                <Input
                    id="support_max_attachments"
                    type="number"
                    min={0}
                    max={10}
                    value={data.support_max_attachments}
                    onChange={(e) => setData('support_max_attachments', Number(e.target.value))}
                    className="w-40"
                    required
                />
                <p className="text-muted-foreground text-xs">{t('Set to 0 to disable ticket attachments.')}</p>
                <InputError message={errors.support_max_attachments} />
            </div>

            <div className="grid gap-3">
                <Label>{t('Canned responses')}</Label>
                {data.support_canned_responses.map((response, index) => (
                    <div key={index} className="grid gap-2 rounded-md border p-3">
                        <div className="flex items-center gap-2">
                            <Input
                                value={response.title}
                                placeholder={t('Title')}
                                maxLength={100}
                                onChange={(e) =>
                                    setData(
                                        'support_canned_responses',
                                        data.support_canned_responses.map((item, i) => (i === index ? { ...item, title: e.target.value } : item)),
                                    )
                                }
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={t('Remove response')}
                                onClick={() =>
                                    setData(
                                        'support_canned_responses',
                                        data.support_canned_responses.filter((_, i) => i !== index),
                                    )
                                }
                            >
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        </div>
                        <textarea
                            value={response.body}
                            placeholder={t('Response text')}
                            rows={2}
                            maxLength={2000}
                            className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                            onChange={(e) =>
                                setData(
                                    'support_canned_responses',
                                    data.support_canned_responses.map((item, i) => (i === index ? { ...item, body: e.target.value } : item)),
                                )
                            }
                        />
                        <InputError message={errors[`support_canned_responses.${index}.title` as keyof typeof errors]} />
                        <InputError message={errors[`support_canned_responses.${index}.body` as keyof typeof errors]} />
                    </div>
                ))}
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="w-fit"
                    disabled={data.support_canned_responses.length >= 20}
                    onClick={() => setData('support_canned_responses', [...data.support_canned_responses, { title: '', body: '' }])}
                >
                    <Plus className="mr-1 h-4 w-4" />
                    {t('Add response')}
                </Button>
                <InputError message={errors.support_canned_responses} />
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}
