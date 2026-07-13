import { BlockFields } from '@/components/admin/blocks/block-fields';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BlockPayload, type BlockSchema, type BreadcrumbItem, type EditableBlock } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Copy, ExternalLink, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface PageSummary {
    id: number;
    title: string;
    slug: string;
    is_home: boolean;
    is_published: boolean;
}

interface BlocksProps {
    page: PageSummary;
    blocks: EditableBlock[];
    schema: BlockSchema[];
}

export default function PageBlocksIndex({ page, blocks, schema }: BlocksProps) {
    const t = useTrans();
    const [editing, setEditing] = useState<EditableBlock | null>(null);
    const [adding, setAdding] = useState<BlockSchema | null>(null);
    const [type, setType] = useState(schema[0]?.type ?? '');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Pages'), href: '/admin/pages' },
        { title: page.title, href: route('admin.pages.blocks.index', page.id) },
    ];

    const move = (index: number, direction: -1 | 1) => {
        const next = [...blocks];
        const target = index + direction;

        if (target < 0 || target >= next.length) {
            return;
        }

        [next[index], next[target]] = [next[target], next[index]];

        router.post(route('admin.pages.blocks.reorder', page.id), { ids: next.map((block) => block.id) }, { preserveScroll: true });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Blocks — :page', { page: page.title })} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-xl font-semibold">{t('Blocks — :page', { page: page.title })}</h1>
                        <p className="text-muted-foreground text-sm">
                            {page.is_home
                                ? t('This is the storefront home page. Its blocks are what a visitor sees on the front page.')
                                : t('Blocks replace this page’s written body. Remove them all to go back to markdown.')}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <a href={page.is_home ? '/' : `/p/${page.slug}`} target="_blank" rel="noopener">
                                <ExternalLink className="h-4 w-4" />
                                {t('View page')}
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={route('admin.pages.index')}>{t('All pages')}</Link>
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardContent className="flex flex-wrap items-end gap-3 pt-6">
                        <div className="grid gap-2">
                            <Label htmlFor="type">{t('Block type')}</Label>
                            <Select value={type} onValueChange={setType}>
                                <SelectTrigger id="type" className="w-64">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {schema.map((block) => (
                                        <SelectItem key={block.type} value={block.type}>
                                            {block.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <Button onClick={() => setAdding(schema.find((block) => block.type === type) ?? null)}>
                            <Plus className="h-4 w-4" />
                            {t('Add block')}
                        </Button>
                    </CardContent>
                </Card>

                {blocks.length === 0 ? (
                    <div className="text-muted-foreground rounded-xl border border-dashed py-16 text-center text-sm">{t('No blocks yet.')}</div>
                ) : (
                    <div className="space-y-3">
                        {blocks.map((block, index) => (
                            <Card key={block.id}>
                                <CardContent className="flex flex-wrap items-center justify-between gap-3 pt-6">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-medium">{block.label ?? block.type}</p>
                                            {!block.is_active && <Badge variant="outline">{t('Hidden')}</Badge>}
                                            {block.starts_at !== null && (
                                                <Badge variant="outline">{t('From :date', { date: block.starts_at })}</Badge>
                                            )}
                                            {block.ends_at !== null && <Badge variant="outline">{t('Until :date', { date: block.ends_at })}</Badge>}
                                            {block.label === null && <Badge variant="destructive">{t('Unknown type')}</Badge>}
                                        </div>
                                        <p className="text-muted-foreground line-clamp-1 text-sm">{summarize(block)}</p>
                                    </div>

                                    <div className="flex gap-1">
                                        <Button variant="ghost" size="icon" aria-label={t('Move up')} onClick={() => move(index, -1)}>
                                            <ArrowUp className="h-4 w-4" />
                                        </Button>
                                        <Button variant="ghost" size="icon" aria-label={t('Move down')} onClick={() => move(index, 1)}>
                                            <ArrowDown className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Duplicate')}
                                            onClick={() =>
                                                router.post(route('admin.pages.blocks.duplicate', [page.id, block.id]), {}, { preserveScroll: true })
                                            }
                                        >
                                            <Copy className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Edit')}
                                            disabled={block.label === null}
                                            onClick={() => setEditing(block)}
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Delete')}
                                            onClick={() =>
                                                router.delete(route('admin.pages.blocks.destroy', [page.id, block.id]), { preserveScroll: true })
                                            }
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {adding !== null && <BlockDialog page={page} schema={adding} block={null} onClose={() => setAdding(null)} />}

            {editing !== null && (
                <BlockDialog
                    key={editing.id}
                    page={page}
                    schema={schema.find((block) => block.type === editing.type) ?? null}
                    block={editing}
                    onClose={() => setEditing(null)}
                />
            )}
        </AdminLayout>
    );
}

/** One line of the block's own content, so a list of nine blocks is readable. */
function summarize(block: EditableBlock): string {
    const payload = block.payload;
    const candidate = payload.heading ?? payload.title ?? payload.body ?? payload.placement ?? payload.size;

    return typeof candidate === 'string' ? candidate : '';
}

function BlockDialog({
    page,
    schema,
    block,
    onClose,
}: {
    page: PageSummary;
    schema: BlockSchema | null;
    block: EditableBlock | null;
    onClose: () => void;
}) {
    const t = useTrans();

    const { data, setData, post, put, processing, errors } = useForm<{
        type: string;
        payload: BlockPayload;
        is_active: boolean;
        starts_at: string;
        ends_at: string;
    }>({
        type: schema?.type ?? '',
        payload: block === null ? { ...(schema?.defaults ?? {}) } : { ...(schema?.defaults ?? {}), ...block.payload },
        is_active: block?.is_active ?? true,
        starts_at: block?.starts_at ?? '',
        ends_at: block?.ends_at ?? '',
    });

    if (schema === null) {
        return null;
    }

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onClose() };

        if (block === null) {
            post(route('admin.pages.blocks.store', page.id), options);
        } else {
            put(route('admin.pages.blocks.update', [page.id, block.id]), options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{block === null ? t('Add :block', { block: schema.label }) : t('Edit :block', { block: schema.label })}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <BlockFields
                        fields={schema.fields}
                        values={data.payload}
                        onChange={(name, value) => setData('payload', { ...data.payload, [name]: value })}
                        errors={errors as Record<string, string>}
                        path="payload"
                        imageUrls={block?.image_urls ?? {}}
                    />

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="starts_at">{t('Show from')}</Label>
                            <Input id="starts_at" type="date" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} />
                            <InputError message={errors.starts_at} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ends_at">{t('Show until')}</Label>
                            <Input id="ends_at" type="date" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} />
                            <InputError message={errors.ends_at} />
                        </div>
                    </div>

                    <label className="flex items-center justify-between gap-4 text-sm">
                        <span className="font-medium">{t('Visible')}</span>
                        <Switch checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
                    </label>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {t('Save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
