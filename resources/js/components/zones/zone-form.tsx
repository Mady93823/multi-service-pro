import InputError from '@/components/input-error';
import { ZonePolygonEditor } from '@/components/maps/zone-polygon-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useTrans } from '@/lib/i18n';
import { type City, type GeoJsonPolygon, type Zone } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

type ZoneForm = {
    city_id: string;
    name: string;
    geojson: GeoJsonPolygon | null;
    is_active: boolean;
};

interface ZoneFormProps {
    zone?: Zone;
    cities: City[];
}

export function ZoneForm({ zone, cities }: ZoneFormProps) {
    const isEdit = zone !== undefined;
    const t = useTrans();

    const { data, setData, post, put, processing, errors } = useForm<ZoneForm>({
        city_id: zone?.city_id ? String(zone.city_id) : cities[0]?.id ? String(cities[0].id) : '',
        name: zone?.name ?? '',
        geojson: zone?.geojson ?? null,
        is_active: zone?.is_active ?? true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (isEdit) {
            put(route('admin.zones.update', zone.id));
        } else {
            post(route('admin.zones.store'));
        }
    };

    // A zone cannot exist without a city (M25) — say so instead of showing an
    // empty picker that can only fail on submit.
    if (cities.length === 0) {
        return (
            <div className="max-w-xl space-y-4">
                <p className="text-muted-foreground text-sm">{t('Add a city first — every zone belongs to one.')}</p>
                <Button asChild>
                    <Link href={route('admin.cities.create')}>{t('Add city')}</Link>
                </Button>
            </div>
        );
    }

    return (
        <form onSubmit={submit} className="max-w-3xl space-y-6">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="name">{t('Zone name')}</Label>
                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="city_id">{t('City')}</Label>
                    <Select value={data.city_id} onValueChange={(value) => setData('city_id', value)}>
                        <SelectTrigger id="city_id">
                            <SelectValue placeholder={t('Select a city')} />
                        </SelectTrigger>
                        <SelectContent>
                            {cities.map((city) => (
                                <SelectItem key={city.id} value={String(city.id)}>
                                    {city.name}
                                    {city.is_active ? '' : ` — ${t('inactive')}`}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.city_id} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label>{t('Service area boundary')}</Label>
                <p className="text-muted-foreground text-sm">{t('Use the polygon tool on the map to outline where this zone provides service.')}</p>
                <ZonePolygonEditor value={data.geojson} onChange={(polygon) => setData('geojson', polygon)} />
                <InputError message={errors.geojson} />
            </div>

            <div className="flex items-center gap-3">
                <Switch id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
                <Label htmlFor="is_active">{t('Active (customers inside this area are served)')}</Label>
            </div>

            <div className="flex gap-2">
                <Button type="submit" disabled={processing}>
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    {isEdit ? t('Save changes') : t('Create zone')}
                </Button>
                <Button asChild variant="outline">
                    <Link href={route('admin.zones.index')}>{t('Cancel')}</Link>
                </Button>
            </div>
        </form>
    );
}
