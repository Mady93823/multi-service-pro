import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/lib/i18n';
import { type Faq } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface FaqFormProps {
    faq?: Faq;
}

export function FaqForm({ faq }: FaqFormProps) {
    const t = useTrans();

    const { data, setData, post, put, processing, errors } = useForm({
        question: faq?.question ?? '',
        answer: faq?.answer ?? '',
        is_active: faq?.is_active ?? true,
        sort_order: faq?.sort_order ?? 0,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (faq) {
            put(route('admin.faqs.update', faq.id));
        } else {
            post(route('admin.faqs.store'));
        }
    };

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-6">
            <div className="space-y-2">
                <Label htmlFor="question">{t('Question')}</Label>
                <Input id="question" value={data.question} onChange={(e) => setData('question', e.target.value)} required maxLength={255} />
                <InputError message={errors.question} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="answer">{t('Answer')}</Label>
                <Textarea id="answer" value={data.answer} onChange={(e) => setData('answer', e.target.value)} rows={5} required />
                <InputError message={errors.answer} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="flex items-center gap-3">
                    <Switch id="is_active" checked={data.is_active} onCheckedChange={(v) => setData('is_active', v)} />
                    <Label htmlFor="is_active">{t('Active')}</Label>
                </div>
                <div className="space-y-2">
                    <Label htmlFor="sort_order">{t('Sort order')}</Label>
                    <Input
                        id="sort_order"
                        type="number"
                        min={0}
                        value={data.sort_order}
                        onChange={(e) => setData('sort_order', Number(e.target.value))}
                    />
                    <InputError message={errors.sort_order} />
                </div>
            </div>

            <div className="flex gap-2">
                <Button type="submit" disabled={processing}>
                    {faq ? t('Save changes') : t('Create FAQ')}
                </Button>
                <Button asChild variant="outline" type="button">
                    <Link href={route('admin.faqs.index')}>{t('Cancel')}</Link>
                </Button>
            </div>
        </form>
    );
}
