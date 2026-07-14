import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/lib/i18n';
import { type CmsPage } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface PageFormProps {
    page?: CmsPage;
}

export function PageForm({ page }: PageFormProps) {
    const t = useTrans();

    const { data, setData, post, put, processing, errors } = useForm({
        title: page?.title ?? '',
        slug: page?.slug ?? '',
        body: page?.body ?? '',
        // M24: blank falls back to the site-wide SEO defaults.
        meta_title: page?.meta_title ?? '',
        meta_description: page?.meta_description ?? '',
        is_published: page?.is_published ?? false,
        show_in_footer: page?.show_in_footer ?? false,
        sort_order: page?.sort_order ?? 0,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (page) {
            put(route('admin.pages.update', page.id));
        } else {
            post(route('admin.pages.store'));
        }
    };

    return (
        <form onSubmit={submit} className="max-w-3xl space-y-6">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="title">{t('Title')}</Label>
                    <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} required maxLength={150} />
                    <InputError message={errors.title} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="slug">{t('Slug')}</Label>
                    <Input id="slug" value={data.slug} onChange={(e) => setData('slug', e.target.value)} placeholder={t('Auto from title')} />
                    <p className="text-muted-foreground text-xs">{t('Public URL: /p/:slug', { slug: data.slug || '…' })}</p>
                    <InputError message={errors.slug} />
                </div>
            </div>

            <div className="space-y-2">
                <Label htmlFor="body">{t('Body (Markdown)')}</Label>
                <Textarea
                    id="body"
                    value={data.body}
                    onChange={(e) => setData('body', e.target.value)}
                    rows={16}
                    required
                    className="font-mono text-sm"
                />
                <p className="text-muted-foreground text-xs">{t('Headings, lists and links are supported. Raw HTML is stripped on display.')}</p>
                <InputError message={errors.body} />
            </div>

            <div className="space-y-4 rounded-lg border p-4">
                <h3 className="text-sm font-medium">{t('SEO')}</h3>
                <div className="space-y-2">
                    <Label htmlFor="meta_title">{t('Meta title')}</Label>
                    <Input
                        id="meta_title"
                        value={data.meta_title}
                        onChange={(e) => setData('meta_title', e.target.value)}
                        maxLength={70}
                        placeholder={data.title || t('Falls back to the page title')}
                    />
                    <InputError message={errors.meta_title} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="meta_description">{t('Meta description')}</Label>
                    <Textarea
                        id="meta_description"
                        value={data.meta_description}
                        onChange={(e) => setData('meta_description', e.target.value)}
                        maxLength={200}
                        rows={2}
                        placeholder={t('Falls back to the site default')}
                    />
                    <InputError message={errors.meta_description} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="flex items-center gap-3">
                    <Switch id="is_published" checked={data.is_published} onCheckedChange={(v) => setData('is_published', v)} />
                    <Label htmlFor="is_published">{t('Published')}</Label>
                </div>
                <div className="flex items-center gap-3">
                    <Switch id="show_in_footer" checked={data.show_in_footer} onCheckedChange={(v) => setData('show_in_footer', v)} />
                    <Label htmlFor="show_in_footer">{t('Show in footer')}</Label>
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
                    {page ? t('Save changes') : t('Create page')}
                </Button>
                <Button asChild variant="outline" type="button">
                    <Link href={route('admin.pages.index')}>{t('Cancel')}</Link>
                </Button>
            </div>
        </form>
    );
}
