import InputError from '@/components/input-error';
import { AddressPinPicker, type PinPoint } from '@/components/maps/address-pin-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useTrans } from '@/lib/i18n';
import { type City } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

type CityForm = {
    name: string;
    slug: string;
    state: string;
    timezone: string;
    center_lat: number;
    center_lng: number;
    is_active: boolean;
    cash_enabled: boolean;
    sort_order: number;
};

interface CityFormProps {
    city?: City;
    timezones: string[];
}

export function CityForm({ city, timezones }: CityFormProps) {
    const isEdit = city !== undefined;
    const t = useTrans();

    const { data, setData, post, put, processing, errors } = useForm<CityForm>({
        name: city?.name ?? '',
        slug: city?.slug ?? '',
        state: city?.state ?? '',
        timezone: city?.timezone ?? 'Asia/Kolkata',
        center_lat: city?.center_lat ?? 12.9716,
        center_lng: city?.center_lng ?? 77.5946,
        is_active: city?.is_active ?? true,
        cash_enabled: city?.cash_enabled ?? true,
        sort_order: city?.sort_order ?? 0,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (isEdit) {
            put(route('admin.cities.update', city.id));
        } else {
            post(route('admin.cities.store'));
        }
    };

    const pin: PinPoint = { lat: data.center_lat, lng: data.center_lng };

    return (
        <form onSubmit={submit} className="max-w-3xl space-y-6">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="name">{t('City name')}</Label>
                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="state">{t('State or region')}</Label>
                    <Input id="state" value={data.state} onChange={(e) => setData('state', e.target.value)} />
                    <InputError message={errors.state} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="slug">{t('URL slug')}</Label>
                    <Input
                        id="slug"
                        value={data.slug}
                        onChange={(e) => setData('slug', e.target.value)}
                        placeholder={t('Left blank, it is made from the name')}
                    />
                    <InputError message={errors.slug} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="timezone">{t('Timezone')}</Label>
                    <Select value={data.timezone} onValueChange={(value) => setData('timezone', value)}>
                        <SelectTrigger id="timezone">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent className="max-h-72">
                            {timezones.map((zone) => (
                                <SelectItem key={zone} value={zone}>
                                    {zone}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <p className="text-muted-foreground text-xs">{t('Booking slots in this city are offered in its own local time.')}</p>
                    <InputError message={errors.timezone} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label>{t('City centre')}</Label>
                <p className="text-muted-foreground text-sm">{t('Where the map opens for this city. Drag the pin or search for a place.')}</p>
                <AddressPinPicker
                    value={pin}
                    onChange={(point) => {
                        setData((current) => ({ ...current, center_lat: point.lat, center_lng: point.lng }));
                    }}
                />
                <InputError message={errors.center_lat ?? errors.center_lng} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
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

                <div className="flex items-end gap-3 pb-2">
                    <Switch id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
                    <Label htmlFor="is_active">{t('Active (shown on the storefront)')}</Label>
                </div>
            </div>

            <div className="space-y-1">
                <div className="flex items-center gap-3">
                    <Switch id="cash_enabled" checked={data.cash_enabled} onCheckedChange={(checked) => setData('cash_enabled', checked)} />
                    <Label htmlFor="cash_enabled">{t('Accept pay after service (cash) in this city')}</Label>
                </div>
                <p className="text-muted-foreground text-sm">
                    {t('Off, every zone in this city takes online payment only — the per-zone switch cannot re-enable it.')}
                </p>
            </div>

            <div className="flex gap-2">
                <Button type="submit" disabled={processing}>
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    {isEdit ? t('Save changes') : t('Create city')}
                </Button>
                <Button asChild variant="outline">
                    <Link href={route('admin.cities.index')}>{t('Cancel')}</Link>
                </Button>
            </div>
        </form>
    );
}
