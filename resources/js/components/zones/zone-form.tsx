import InputError from '@/components/input-error';
import { ZonePolygonEditor } from '@/components/maps/zone-polygon-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTrans } from '@/lib/i18n';
import { type GeoJsonPolygon, type Zone } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

type ZoneForm = {
    name: string;
    city: string;
    geojson: GeoJsonPolygon | null;
    is_active: boolean;
};

interface ZoneFormProps {
    zone?: Zone;
}

export function ZoneForm({ zone }: ZoneFormProps) {
    const isEdit = zone !== undefined;
    const t = useTrans();

    const { data, setData, post, put, processing, errors } = useForm<ZoneForm>({
        name: zone?.name ?? '',
        city: zone?.city ?? '',
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

    return (
        <form onSubmit={submit} className="max-w-3xl space-y-6">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="name">{t('Zone name')}</Label>
                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="city">{t('City')}</Label>
                    <Input id="city" value={data.city} onChange={(e) => setData('city', e.target.value)} required />
                    <InputError message={errors.city} />
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
