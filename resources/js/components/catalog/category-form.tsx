import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useTrans } from '@/lib/i18n';
import { type Category } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

const NONE = 'none';

type CategoryForm = {
    _method?: string;
    name: string;
    parent_id: string;
    /** Which storefront surface (services page vs Event Management) — roots only. */
    type: string;
    sort_order: number;
    is_active: boolean;
    icon: File | null;
    image: File | null;
    /** Blank = inherit the parent category, then the platform rate (M09). */
    commission_percent: string;
};

interface CategoryFormProps {
    parents: Category[];
    category?: Category;
}

export function CategoryForm({ parents, category }: CategoryFormProps) {
    const isEdit = category !== undefined;
    const t = useTrans();

    const { data, setData, post, processing, errors, transform } = useForm<CategoryForm>({
        ...(isEdit ? { _method: 'put' } : {}),
        name: category?.name ?? '',
        parent_id: category?.parent_id?.toString() ?? NONE,
        type: category?.type ?? 'service',
        sort_order: category?.sort_order ?? 0,
        is_active: category?.is_active ?? true,
        icon: null,
        image: null,
        commission_percent: category?.commission_percent ?? '',
    });

    transform((current) => ({
        ...current,
        parent_id: current.parent_id === NONE ? null : Number(current.parent_id),
    }));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (isEdit) {
            post(route('admin.categories.update', category.id), { forceFormData: true });
        } else {
            post(route('admin.categories.store'), { forceFormData: true });
        }
    };

    return (
        <form onSubmit={submit} className="max-w-xl space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="name">{t('Name')}</Label>
                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="parent_id">{t('Parent category')}</Label>
                <Select value={data.parent_id} onValueChange={(value) => setData('parent_id', value)}>
                    <SelectTrigger id="parent_id">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NONE}>{t('None (top level)')}</SelectItem>
                        {parents.map((parent) => (
                            <SelectItem key={parent.id} value={parent.id.toString()}>
                                {parent.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.parent_id} />
            </div>

            {data.parent_id === NONE ? (
                <div className="grid gap-2">
                    <Label htmlFor="type">{t('Shown on')}</Label>
                    <Select value={data.type} onValueChange={(value) => setData('type', value)}>
                        <SelectTrigger id="type">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="service">{t('Services page')}</SelectItem>
                            <SelectItem value="event">{t('Event Management page')}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p className="text-muted-foreground text-xs">
                        {t('Event categories (weddings, birthdays, kitty parties...) get their own page; booking works the same.')}
                    </p>
                    <InputError message={errors.type} />
                </div>
            ) : (
                <p className="text-muted-foreground text-xs">{t('A sub-category is shown wherever its parent is shown.')}</p>
            )}

            <div className="grid gap-2">
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

            <div className="grid gap-2">
                <Label htmlFor="commission_percent">{t('Commission override (%)')}</Label>
                <Input
                    id="commission_percent"
                    type="number"
                    min={0}
                    max={100}
                    step="0.01"
                    value={data.commission_percent}
                    onChange={(e) => setData('commission_percent', e.target.value)}
                    placeholder={t('Leave blank to use the platform rate')}
                />
                <InputError message={errors.commission_percent} />
            </div>

            <div className="flex items-center gap-3">
                <Switch id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
                <Label htmlFor="is_active">{t('Active (visible to customers)')}</Label>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="icon">{t('Icon')}</Label>
                {category?.icon_url && <img src={category.icon_url} alt="" className="h-10 w-10 rounded object-cover" />}
                <Input id="icon" type="file" accept="image/*" onChange={(e) => setData('icon', e.target.files?.[0] ?? null)} />
                <InputError message={errors.icon} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="image">{t('Banner image')}</Label>
                {category?.image_url && <img src={category.image_url} alt="" className="h-20 rounded object-cover" />}
                <Input id="image" type="file" accept="image/*" onChange={(e) => setData('image', e.target.files?.[0] ?? null)} />
                <InputError message={errors.image} />
            </div>

            <div className="flex gap-2">
                <Button type="submit" disabled={processing}>
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    {isEdit ? t('Save changes') : t('Create category')}
                </Button>
                <Button asChild variant="outline">
                    <Link href={route('admin.categories.index')}>{t('Cancel')}</Link>
                </Button>
            </div>
        </form>
    );
}
