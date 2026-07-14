import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/lib/i18n';
import { type Category, type PricingType, type Service, type Zone } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle, Plus, X } from 'lucide-react';
import { FormEventHandler } from 'react';

type AddonInput = {
    name: string;
    price: string;
    is_active: boolean;
};

type ServiceForm = {
    _method?: string;
    category_id: string;
    name: string;
    short_description: string;
    description: string;
    meta_title: string;
    meta_description: string;
    pricing_type: PricingType;
    price: string;
    duration_minutes: string;
    sort_order: number;
    is_featured: boolean;
    is_active: boolean;
    addons: AddonInput[];
    related_ids: number[];
    zone_ids: number[];
    image: File | null;
};

const pricingTypeValues: PricingType[] = ['fixed', 'hourly', 'inspection'];

interface ServiceFormProps {
    categories: Category[];
    relatable: Service[];
    zones: Zone[];
    service?: Service;
}

export function ServiceForm({ categories, relatable, zones, service }: ServiceFormProps) {
    const isEdit = service !== undefined;
    const t = useTrans();

    const { data, setData, post, processing, errors } = useForm<ServiceForm>({
        ...(isEdit ? { _method: 'put' } : {}),
        category_id: service?.category_id.toString() ?? '',
        name: service?.name ?? '',
        short_description: service?.short_description ?? '',
        description: service?.description ?? '',
        // M24: blank falls back to the site-wide SEO defaults.
        meta_title: service?.meta_title ?? '',
        meta_description: service?.meta_description ?? '',
        pricing_type: service?.pricing_type ?? 'fixed',
        price: service?.price ?? '',
        duration_minutes: service?.duration_minutes?.toString() ?? '',
        sort_order: service?.sort_order ?? 0,
        is_featured: service?.is_featured ?? false,
        is_active: service?.is_active ?? true,
        addons: service?.addons?.map((addon) => ({ name: addon.name, price: addon.price, is_active: addon.is_active })) ?? [],
        related_ids: service?.related?.map((related) => related.id) ?? [],
        zone_ids: service?.zone_ids ?? [],
        image: null,
    });

    const fieldErrors = errors as Record<string, string>;

    const pricingTypeLabels: Record<PricingType, string> = {
        fixed: t('Fixed price'),
        hourly: t('Per hour'),
        inspection: t('Inspection first'),
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (isEdit) {
            post(route('admin.services.update', service.id), { forceFormData: true });
        } else {
            post(route('admin.services.store'), { forceFormData: true });
        }
    };

    const addAddon = () => setData('addons', [...data.addons, { name: '', price: '', is_active: true }]);

    const removeAddon = (index: number) =>
        setData(
            'addons',
            data.addons.filter((_, i) => i !== index),
        );

    const updateAddon = (index: number, patch: Partial<AddonInput>) =>
        setData(
            'addons',
            data.addons.map((addon, i) => (i === index ? { ...addon, ...patch } : addon)),
        );

    const toggleRelated = (id: number, checked: boolean) =>
        setData('related_ids', checked ? [...data.related_ids, id] : data.related_ids.filter((current) => current !== id));

    const toggleZone = (id: number, checked: boolean) =>
        setData('zone_ids', checked ? [...data.zone_ids, id] : data.zone_ids.filter((current) => current !== id));

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="name">{t('Name')}</Label>
                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-6 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="category_id">{t('Category')}</Label>
                    <Select value={data.category_id} onValueChange={(value) => setData('category_id', value)}>
                        <SelectTrigger id="category_id">
                            <SelectValue placeholder={t('Choose a category')} />
                        </SelectTrigger>
                        <SelectContent>
                            {categories.map((category) => (
                                <SelectItem key={category.id} value={category.id.toString()}>
                                    {category.parent_id !== null ? `— ${category.name}` : category.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.category_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="pricing_type">{t('Pricing type')}</Label>
                    <Select value={data.pricing_type} onValueChange={(value) => setData('pricing_type', value as PricingType)}>
                        <SelectTrigger id="pricing_type">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {pricingTypeValues.map((value) => (
                                <SelectItem key={value} value={value}>
                                    {pricingTypeLabels[value]}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.pricing_type} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="price">{t('Price')}</Label>
                    <Input
                        id="price"
                        type="number"
                        min={0}
                        step="0.01"
                        value={data.price}
                        onChange={(e) => setData('price', e.target.value)}
                        required
                    />
                    <InputError message={errors.price} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="duration_minutes">{t('Duration (minutes)')}</Label>
                    <Input
                        id="duration_minutes"
                        type="number"
                        min={5}
                        max={1440}
                        value={data.duration_minutes}
                        onChange={(e) => setData('duration_minutes', e.target.value)}
                    />
                    <InputError message={errors.duration_minutes} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="short_description">{t('Short description')}</Label>
                <Input
                    id="short_description"
                    value={data.short_description}
                    onChange={(e) => setData('short_description', e.target.value)}
                    maxLength={255}
                />
                <InputError message={errors.short_description} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">{t('Description')}</Label>
                <Textarea id="description" rows={5} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                <InputError message={errors.description} />
            </div>

            <div className="space-y-4 rounded-lg border p-4">
                <h3 className="text-sm font-medium">{t('SEO')}</h3>
                <div className="grid gap-2">
                    <Label htmlFor="meta_title">{t('Meta title')}</Label>
                    <Input
                        id="meta_title"
                        value={data.meta_title}
                        onChange={(e) => setData('meta_title', e.target.value)}
                        maxLength={70}
                        placeholder={data.name || t('Falls back to the service name')}
                    />
                    <InputError message={errors.meta_title} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="meta_description">{t('Meta description')}</Label>
                    <Textarea
                        id="meta_description"
                        rows={2}
                        value={data.meta_description}
                        onChange={(e) => setData('meta_description', e.target.value)}
                        maxLength={200}
                        placeholder={t('Falls back to the short description, then the site default')}
                    />
                    <InputError message={errors.meta_description} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="image">{t('Image')}</Label>
                {service?.image_card_url && <img src={service.image_card_url} alt="" className="h-24 rounded object-cover" />}
                <Input id="image" type="file" accept="image/*" onChange={(e) => setData('image', e.target.files?.[0] ?? null)} />
                <InputError message={errors.image} />
            </div>

            <div className="grid gap-6 sm:grid-cols-3">
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

                <div className="flex items-center gap-3 pt-6">
                    <Switch id="is_featured" checked={data.is_featured} onCheckedChange={(checked) => setData('is_featured', checked)} />
                    <Label htmlFor="is_featured">{t('Featured')}</Label>
                </div>

                <div className="flex items-center gap-3 pt-6">
                    <Switch id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
                    <Label htmlFor="is_active">{t('Active')}</Label>
                </div>
            </div>

            <div className="space-y-3">
                <div className="flex items-center justify-between">
                    <Label>{t('Add-ons')}</Label>
                    <Button type="button" variant="outline" size="sm" onClick={addAddon}>
                        <Plus className="h-4 w-4" />
                        {t('Add add-on')}
                    </Button>
                </div>
                {data.addons.map((addon, index) => (
                    <div key={index} className="flex items-start gap-2">
                        <div className="grid flex-1 gap-1">
                            <Input placeholder={t('Add-on name')} value={addon.name} onChange={(e) => updateAddon(index, { name: e.target.value })} />
                            <InputError message={fieldErrors[`addons.${index}.name`]} />
                        </div>
                        <div className="grid w-32 gap-1">
                            <Input
                                type="number"
                                min={0}
                                step="0.01"
                                placeholder={t('Price')}
                                value={addon.price}
                                onChange={(e) => updateAddon(index, { price: e.target.value })}
                            />
                            <InputError message={fieldErrors[`addons.${index}.price`]} />
                        </div>
                        <div className="flex items-center gap-2 pt-2">
                            <Switch checked={addon.is_active} onCheckedChange={(checked) => updateAddon(index, { is_active: checked })} />
                            <Button type="button" variant="ghost" size="icon" onClick={() => removeAddon(index)} aria-label={t('Remove add-on')}>
                                <X className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                ))}
            </div>

            {zones.length > 0 && (
                <div className="space-y-3">
                    <Label>{t('Available in zones')}</Label>
                    <p className="text-muted-foreground text-sm">{t('Leave all unchecked to offer this service in every zone.')}</p>
                    <div className="grid max-h-48 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2">
                        {zones.map((zone) => (
                            <label key={zone.id} className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={data.zone_ids.includes(zone.id)}
                                    onCheckedChange={(checked) => toggleZone(zone.id, checked === true)}
                                />
                                {zone.name} <span className="text-muted-foreground">({zone.city_name})</span>
                            </label>
                        ))}
                    </div>
                    <InputError message={errors.zone_ids} />
                </div>
            )}

            {relatable.length > 0 && (
                <div className="space-y-3">
                    <Label>{t('Related services (cross-sell)')}</Label>
                    <div className="grid max-h-48 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2">
                        {relatable.map((candidate) => (
                            <label key={candidate.id} className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={data.related_ids.includes(candidate.id)}
                                    onCheckedChange={(checked) => toggleRelated(candidate.id, checked === true)}
                                />
                                {candidate.name}
                            </label>
                        ))}
                    </div>
                    <InputError message={errors.related_ids} />
                </div>
            )}

            <div className="flex gap-2">
                <Button type="submit" disabled={processing}>
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    {isEdit ? t('Save changes') : t('Create service')}
                </Button>
                <Button asChild variant="outline">
                    <Link href={route('admin.services.index')}>{t('Cancel')}</Link>
                </Button>
            </div>
        </form>
    );
}
