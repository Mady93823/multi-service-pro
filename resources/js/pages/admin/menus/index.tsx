import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Option {
    value: string;
    label: string;
}

interface Item {
    id: number;
    label: string;
    type: string;
    target: string | null;
    visibility: string;
    is_active: boolean;
    parent_id: number | null;
    children: Item[];
}

interface MenuGroup {
    id: number;
    location: string;
    label: string;
    items: Item[];
}

interface MenusIndexProps {
    menus: MenuGroup[];
    types: Option[];
    visibilities: Option[];
    routes: Option[];
    pages: Option[];
}

/**
 * The storefront's navigation (M19). One menu per location; the admin edits its
 * items. Reordering is up/down rather than drag-and-drop — a menu is a handful
 * of links, and a keyboard-reachable button beats a pointer-only gesture.
 */
export default function MenusIndex({ menus, types, visibilities, routes, pages }: MenusIndexProps) {
    const t = useTrans();
    const [editing, setEditing] = useState<{ menu: MenuGroup; item: Item | null; parentId: number | null } | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Menus'), href: '/admin/menus' },
    ];

    const move = (menu: MenuGroup, index: number, direction: -1 | 1) => {
        const ids = menu.items.map((item) => item.id);
        const target = index + direction;

        if (target < 0 || target >= ids.length) {
            return;
        }

        [ids[index], ids[target]] = [ids[target], ids[index]];

        router.post(route('admin.menus.reorder', menu.id), { ids }, { preserveScroll: true });
    };

    const remove = (menu: MenuGroup, item: Item) => {
        router.delete(route('admin.menus.items.destroy', [menu.id, item.id]), { preserveScroll: true });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Menus')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('Menus')}</h1>
                <p className="text-muted-foreground text-sm">
                    {t('Links shown in the storefront header and footer. A link that points at a deleted page is hidden automatically.')}
                </p>

                <div className="grid gap-4 lg:grid-cols-2">
                    {menus.map((menu) => (
                        <Card key={menu.id}>
                            <CardHeader className="flex flex-row items-start justify-between gap-2">
                                <div>
                                    <CardTitle>{menu.label}</CardTitle>
                                    <CardDescription>{t(':count links', { count: String(menu.items.length) })}</CardDescription>
                                </div>
                                <Button size="sm" variant="outline" onClick={() => setEditing({ menu, item: null, parentId: null })}>
                                    <Plus className="h-4 w-4" />
                                    {t('Add link')}
                                </Button>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {menu.items.length === 0 ? (
                                    <p className="text-muted-foreground rounded-lg border border-dashed py-6 text-center text-sm">
                                        {t('No links yet.')}
                                    </p>
                                ) : (
                                    menu.items.map((item, index) => (
                                        <div key={item.id} className="rounded-lg border p-2">
                                            <div className="flex items-center gap-2">
                                                <div className="flex flex-col">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-5 w-5"
                                                        aria-label={t('Move up')}
                                                        disabled={index === 0}
                                                        onClick={() => move(menu, index, -1)}
                                                    >
                                                        <ArrowUp className="h-3 w-3" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-5 w-5"
                                                        aria-label={t('Move down')}
                                                        disabled={index === menu.items.length - 1}
                                                        onClick={() => move(menu, index, 1)}
                                                    >
                                                        <ArrowDown className="h-3 w-3" />
                                                    </Button>
                                                </div>

                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium">{item.label}</p>
                                                    <p className="text-muted-foreground truncate text-xs">{item.target}</p>
                                                </div>

                                                {!item.is_active && <Badge variant="outline">{t('Hidden')}</Badge>}
                                                {item.visibility !== 'everyone' && (
                                                    <Badge variant="secondary">
                                                        {visibilities.find((v) => v.value === item.visibility)?.label ?? item.visibility}
                                                    </Badge>
                                                )}

                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={t('Edit link')}
                                                    onClick={() => setEditing({ menu, item, parentId: null })}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={t('Delete link')}
                                                    onClick={() => remove(menu, item)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>

                                            <div className="mt-2 ml-8 space-y-1">
                                                {item.children.map((child) => (
                                                    <div key={child.id} className="flex items-center gap-2">
                                                        <div className="min-w-0 flex-1">
                                                            <p className="truncate text-sm">{child.label}</p>
                                                            <p className="text-muted-foreground truncate text-xs">{child.target}</p>
                                                        </div>
                                                        {!child.is_active && <Badge variant="outline">{t('Hidden')}</Badge>}
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={t('Edit link')}
                                                            onClick={() => setEditing({ menu, item: child, parentId: item.id })}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={t('Delete link')}
                                                            onClick={() => remove(menu, child)}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                ))}

                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-muted-foreground h-7"
                                                    onClick={() => setEditing({ menu, item: null, parentId: item.id })}
                                                >
                                                    <Plus className="h-3 w-3" />
                                                    {t('Add sub-link')}
                                                </Button>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>

            {editing !== null && (
                <ItemDialog
                    key={`${editing.menu.id}-${editing.item?.id ?? 'new'}-${editing.parentId ?? 'root'}`}
                    menu={editing.menu}
                    item={editing.item}
                    parentId={editing.parentId}
                    types={types}
                    visibilities={visibilities}
                    routes={routes}
                    pages={pages}
                    onClose={() => setEditing(null)}
                />
            )}
        </AdminLayout>
    );
}

interface ItemDialogProps {
    menu: MenuGroup;
    item: Item | null;
    parentId: number | null;
    types: Option[];
    visibilities: Option[];
    routes: Option[];
    pages: Option[];
    onClose: () => void;
}

function ItemDialog({ menu, item, parentId, types, visibilities, routes, pages, onClose }: ItemDialogProps) {
    const t = useTrans();

    const { data, setData, post, put, processing, errors } = useForm({
        label: item?.label ?? '',
        type: item?.type ?? 'route',
        target: item?.target ?? '',
        visibility: item?.visibility ?? 'everyone',
        parent_id: item?.parent_id ?? parentId,
        is_active: item?.is_active ?? true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onClose() };

        if (item === null) {
            post(route('admin.menus.items.store', menu.id), options);
        } else {
            put(route('admin.menus.items.update', [menu.id, item.id]), options);
        }
    };

    // The target field follows the type: a route and a page are chosen from what
    // exists, a custom link is typed.
    const options = data.type === 'route' ? routes : data.type === 'page' ? pages : null;

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{item === null ? t('Add link') : t('Edit link')}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="label">{t('Label')}</Label>
                        <Input id="label" value={data.label} onChange={(e) => setData('label', e.target.value)} required />
                        <InputError message={errors.label} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="type">{t('Links to')}</Label>
                        <Select
                            value={data.type}
                            onValueChange={(value) => {
                                setData('type', value);
                                setData('target', '');
                            }}
                        >
                            <SelectTrigger id="type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {types.map((type) => (
                                    <SelectItem key={type.value} value={type.value}>
                                        {type.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.type} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="target">{t('Destination')}</Label>
                        {options !== null ? (
                            <Select value={data.target} onValueChange={(value) => setData('target', value)}>
                                <SelectTrigger id="target">
                                    <SelectValue placeholder={t('Choose…')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        ) : (
                            <Input
                                id="target"
                                value={data.target}
                                onChange={(e) => setData('target', e.target.value)}
                                placeholder="https://example.com"
                                required
                            />
                        )}
                        <InputError message={errors.target} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="visibility">{t('Visible to')}</Label>
                        <Select value={data.visibility} onValueChange={(value) => setData('visibility', value)}>
                            <SelectTrigger id="visibility">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {visibilities.map((visibility) => (
                                    <SelectItem key={visibility.value} value={visibility.value}>
                                        {visibility.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.visibility} />
                    </div>

                    <label className="flex items-center justify-between gap-4 text-sm">
                        <span className="font-medium">{t('Show this link')}</span>
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
