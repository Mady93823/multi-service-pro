import { Pagination } from '@/components/catalog/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type MediaAsset, type Paginated } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { LoaderCircle, Search, Trash2, Upload } from 'lucide-react';
import { FormEventHandler, useRef, useState } from 'react';

interface MediaIndexProps {
    assets: Paginated<MediaAsset>;
    stats: { files: number; bytes: number; in_use: number };
    filters: { search: string };
}

function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function MediaIndex({ assets, stats, filters }: MediaIndexProps) {
    const t = useTrans();
    const [search, setSearch] = useState(filters.search);
    const [pendingDelete, setPendingDelete] = useState<MediaAsset | null>(null);
    const fileInput = useRef<HTMLInputElement>(null);

    const uploadForm = useForm<{ files: File[] }>({ files: [] });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Media'), href: '/admin/media' },
    ];

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('admin.media.index'), search !== '' ? { search } : {}, { preserveState: true, preserveScroll: true });
    };

    const upload = (files: FileList | null) => {
        if (files === null || files.length === 0) {
            return;
        }

        const selected = Array.from(files);

        // setData() is React state: post() on the next line would still send the
        // *previous* data (an empty file list, so the server answered "the files
        // field is required"). transform() runs at submit time, so it wins.
        uploadForm.transform(() => ({ files: selected }));
        uploadForm.post(route('admin.media.store'), {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                uploadForm.reset();

                if (fileInput.current !== null) {
                    fileInput.current.value = '';
                }
            },
        });
    };

    // A rejected file reports under `files.0`, not `files` — show either.
    const uploadError = uploadForm.errors.files ?? Object.entries(uploadForm.errors).find(([key]) => key.startsWith('files.'))?.[1] ?? undefined;

    const confirmDelete = () => {
        if (pendingDelete === null) {
            return;
        }

        router.delete(route('admin.media.destroy', pendingDelete.id), {
            preserveScroll: true,
            onFinish: () => setPendingDelete(null),
        });
    };

    const tiles = [
        { label: t('Files'), value: String(stats.files) },
        { label: t('Storage used'), value: formatSize(stats.bytes) },
        { label: t('In use'), value: String(stats.in_use) },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Media')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold">{t('Media library')}</h1>

                    <Button type="button" disabled={uploadForm.processing} onClick={() => fileInput.current?.click()}>
                        {uploadForm.processing ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />}
                        {t('Upload files')}
                    </Button>
                    <input
                        ref={fileInput}
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        className="hidden"
                        onChange={(e) => upload(e.target.files)}
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    {tiles.map((tile) => (
                        <Card key={tile.label}>
                            <CardContent className="pt-6">
                                <p className="text-muted-foreground text-xs">{tile.label}</p>
                                <p className="text-lg font-semibold">{tile.value}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <form onSubmit={submitSearch} className="flex items-center gap-2">
                    <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('Search files...')} className="w-64" />
                    <Button type="submit" variant="outline" size="icon" aria-label={t('Search')}>
                        <Search className="h-4 w-4" />
                    </Button>
                </form>

                {uploadError !== undefined && <p className="text-destructive text-sm">{uploadError}</p>}

                {assets.data.length === 0 ? (
                    <div className="text-muted-foreground rounded-xl border border-dashed py-16 text-center text-sm">
                        {t('The library is empty. Upload your first file.')}
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                        {assets.data.map((asset) => (
                            <Card key={asset.id} className="overflow-hidden pt-0">
                                {asset.thumb_url !== null ? (
                                    <img src={asset.thumb_url} alt="" className="h-32 w-full object-cover" />
                                ) : (
                                    <div className="bg-muted h-32 w-full" />
                                )}
                                <CardContent className="space-y-2 px-3 pb-3">
                                    <p className="truncate text-sm font-medium" title={asset.name}>
                                        {asset.name}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {formatSize(asset.size)} · {asset.uploaded_at}
                                    </p>
                                    <div className="flex items-center justify-between">
                                        {(asset.usage_count ?? 0) > 0 ? (
                                            <Badge variant="secondary">{t('Used :count×', { count: String(asset.usage_count) })}</Badge>
                                        ) : (
                                            <Badge variant="outline">{t('Unused')}</Badge>
                                        )}
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Delete file')}
                                            onClick={() => setPendingDelete(asset)}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                <Pagination meta={assets.meta} links={assets.links} />
            </div>

            <Dialog open={pendingDelete !== null} onOpenChange={(open) => !open && setPendingDelete(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Delete this file?')}</DialogTitle>
                        <DialogDescription>
                            {(pendingDelete?.usage_count ?? 0) > 0
                                ? t('This file is in use. Remove it where it is used first.')
                                : t('The file is removed from the library. This cannot be undone.')}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setPendingDelete(null)}>
                            {t('Cancel')}
                        </Button>
                        <Button variant="destructive" disabled={(pendingDelete?.usage_count ?? 0) > 0} onClick={confirmDelete}>
                            {t('Delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
