import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type TranslationEntry } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Save, Search } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

interface TranslationsEditorProps {
    language: { id: number; code: string; name: string };
    entries: TranslationEntry[];
}

export default function TranslationsEditor({ language, entries }: TranslationsEditorProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Languages'), href: '/admin/languages' },
        { title: language.name, href: `/admin/languages/${language.id}/translations` },
    ];

    // One state object for the whole catalog; submitted as a single JSON
    // body (the catalog is bigger than PHP's max_input_vars form limit).
    const [values, setValues] = useState<Record<string, string>>(() => Object.fromEntries(entries.map((entry) => [entry.key, entry.value])));
    const [search, setSearch] = useState('');
    const [untranslatedOnly, setUntranslatedOnly] = useState(false);
    const [saving, setSaving] = useState(false);

    const translatedCount = useMemo(() => entries.filter((entry) => (values[entry.key] ?? '').trim() !== '').length, [entries, values]);

    const visible = useMemo(() => {
        const needle = search.trim().toLowerCase();

        return entries.filter((entry) => {
            if (untranslatedOnly && (values[entry.key] ?? '').trim() !== '') {
                return false;
            }

            return needle === '' || entry.source.toLowerCase().includes(needle) || (values[entry.key] ?? '').toLowerCase().includes(needle);
        });
    }, [entries, search, untranslatedOnly, values]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        router.put(
            route('admin.languages.translations.update', language.id),
            { translations: values },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Translate :name', { name: language.name })} />
            <form onSubmit={submit} className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">{t('Translate :name', { name: language.name })}</h1>
                        <p className="text-muted-foreground text-sm">
                            {t(':done of :total strings translated. Blank strings fall back to English.', {
                                done: translatedCount,
                                total: entries.length,
                            })}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button asChild variant="outline" size="sm" type="button">
                            <Link href={route('admin.languages.index')}>
                                <ArrowLeft className="h-4 w-4" />
                                {t('Back')}
                            </Link>
                        </Button>
                        <Button type="submit" size="sm" disabled={saving}>
                            <Save className="h-4 w-4" />
                            {t('Save translations')}
                        </Button>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-4">
                    <div className="relative w-full max-w-sm">
                        <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" aria-hidden />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={t('Search strings…')}
                            className="pl-8"
                            aria-label={t('Search strings…')}
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <Switch id="untranslated" checked={untranslatedOnly} onCheckedChange={setUntranslatedOnly} />
                        <Label htmlFor="untranslated" className="text-sm">
                            {t('Untranslated only')}
                        </Label>
                    </div>
                </div>

                <div className="divide-y rounded-xl border">
                    {visible.length === 0 && <p className="text-muted-foreground p-8 text-center text-sm">{t('No strings match.')}</p>}
                    {visible.map((entry) => (
                        <div key={entry.key} className="grid gap-2 p-3 sm:grid-cols-2 sm:items-center sm:gap-4">
                            <p className="text-sm break-words">{entry.source}</p>
                            <Input
                                value={values[entry.key] ?? ''}
                                onChange={(e) => setValues((prev) => ({ ...prev, [entry.key]: e.target.value }))}
                                lang={language.code}
                                aria-label={entry.source}
                            />
                        </div>
                    ))}
                </div>

                <div className="flex justify-end">
                    <Button type="submit" disabled={saving}>
                        <Save className="h-4 w-4" />
                        {t('Save translations')}
                    </Button>
                </div>
            </form>
        </AdminLayout>
    );
}
