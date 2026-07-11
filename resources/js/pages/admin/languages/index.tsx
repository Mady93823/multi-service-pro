import { ConfirmDelete } from '@/components/confirm-delete';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type Language } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Languages as LanguagesIcon, Plus } from 'lucide-react';
import { FormEventHandler } from 'react';

interface AdminLanguagesIndexProps {
    languages: Language[];
    catalog_size: number;
}

export default function AdminLanguagesIndex({ languages, catalog_size }: AdminLanguagesIndexProps) {
    const t = useTrans();
    const { errors: pageErrors } = usePage().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Languages'), href: '/admin/languages' },
    ];

    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
        name: '',
        native_name: '',
        is_active: true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.languages.store'), { onSuccess: () => reset() });
    };

    const toggleActive = (language: Language) => {
        router.put(route('admin.languages.update', language.id), {
            name: language.name,
            native_name: language.native_name,
            is_active: !language.is_active,
        });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Languages')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center gap-2">
                    <LanguagesIcon className="h-5 w-5" aria-hidden />
                    <h1 className="text-xl font-semibold">{t('Languages')}</h1>
                </div>

                <p className="text-muted-foreground max-w-3xl text-sm">
                    {t(
                        'Translate the interface into any language. The English catalog (:count strings) is generated from source code; other languages fall back to English for anything untranslated. Set the site language under Settings → Localization.',
                        { count: catalog_size },
                    )}
                </p>

                <InputError message={pageErrors.language} />

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Code')}</TableHead>
                                <TableHead>{t('Language')}</TableHead>
                                <TableHead>{t('Translated')}</TableHead>
                                <TableHead>{t('Active')}</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {languages.map((language) => (
                                <TableRow key={language.id}>
                                    <TableCell className="font-mono text-xs">{language.code}</TableCell>
                                    <TableCell className="font-medium">
                                        <span className="flex flex-wrap items-center gap-2">
                                            {language.name}
                                            {language.native_name && language.native_name !== language.name ? (
                                                <span className="text-muted-foreground">({language.native_name})</span>
                                            ) : null}
                                            {language.is_default && <Badge variant="outline">{t('Default')}</Badge>}
                                            {language.is_site_locale && <Badge className="bg-emerald-600 text-white">{t('Site language')}</Badge>}
                                        </span>
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {language.translated_count}/{catalog_size}
                                    </TableCell>
                                    <TableCell>
                                        <Switch
                                            checked={language.is_active}
                                            onCheckedChange={() => toggleActive(language)}
                                            disabled={language.is_default}
                                            aria-label={t('Toggle :name', { name: language.name })}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            {!language.is_default && (
                                                <Button asChild variant="outline" size="sm">
                                                    <Link href={route('admin.languages.translations.edit', language.id)}>{t('Translate')}</Link>
                                                </Button>
                                            )}
                                            {!language.is_default && !language.is_site_locale && (
                                                <ConfirmDelete
                                                    title={t('Delete language?')}
                                                    description={t('“:name” and its translation file will be removed.', { name: language.name })}
                                                    deleteUrl={route('admin.languages.destroy', language.id)}
                                                />
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle className="text-sm font-medium">{t('Add language')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-4">
                            <div className="space-y-2">
                                <Label htmlFor="code">{t('Code')}</Label>
                                <Input
                                    id="code"
                                    value={data.code}
                                    onChange={(e) => setData('code', e.target.value)}
                                    placeholder="hi"
                                    required
                                    maxLength={12}
                                />
                                <InputError message={errors.code} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="name">{t('Name')}</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder={t('Hindi')}
                                    required
                                    maxLength={50}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="native_name">{t('Native name')}</Label>
                                <Input
                                    id="native_name"
                                    value={data.native_name}
                                    onChange={(e) => setData('native_name', e.target.value)}
                                    placeholder="हिन्दी"
                                    maxLength={50}
                                />
                                <InputError message={errors.native_name} />
                            </div>
                            <div className="flex items-end">
                                <Button type="submit" disabled={processing}>
                                    <Plus className="h-4 w-4" />
                                    {t('Add')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
