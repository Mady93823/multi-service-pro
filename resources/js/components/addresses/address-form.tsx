import InputError from '@/components/input-error';
import { AddressPinPicker, type PinPoint, type ReverseGeocodeResult } from '@/components/maps/address-pin-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useTrans } from '@/lib/i18n';
import { type Address, type AddressLabel } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

type AddressForm = {
    label: AddressLabel;
    line1: string;
    line2: string;
    city: string;
    postal_code: string;
    lat: number | null;
    lng: number | null;
    is_default: boolean;
};

interface AddressFormProps {
    address?: Address;
}

const labelValues: AddressLabel[] = ['home', 'work', 'other'];

export function AddressForm({ address }: AddressFormProps) {
    const isEdit = address !== undefined;
    const t = useTrans();

    const labelNames: Record<AddressLabel, string> = {
        home: t('Home'),
        work: t('Work'),
        other: t('Other'),
    };

    const { data, setData, post, put, processing, errors } = useForm<AddressForm>({
        label: address?.label ?? 'home',
        line1: address?.line1 ?? '',
        line2: address?.line2 ?? '',
        city: address?.city ?? '',
        postal_code: address?.postal_code ?? '',
        lat: address?.lat ?? null,
        lng: address?.lng ?? null,
        is_default: address?.is_default ?? false,
    });

    const onPin = (point: PinPoint, reverse: ReverseGeocodeResult | null) => {
        setData((current) => ({
            ...current,
            lat: point.lat,
            lng: point.lng,
            // The pin decides the location; text fields are refilled from it
            // but stay editable for the details only the customer knows.
            line1: reverse !== null && reverse.line1 !== '' ? reverse.line1 : current.line1,
            line2: reverse !== null && reverse.line2 !== null ? reverse.line2 : current.line2,
            city: reverse !== null && reverse.city !== '' ? reverse.city : current.city,
            postal_code: reverse !== null && reverse.postal_code !== '' ? reverse.postal_code : current.postal_code,
        }));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (isEdit) {
            put(route('addresses.update', address.id));
        } else {
            post(route('addresses.store'));
        }
    };

    return (
        <form onSubmit={submit} className="max-w-3xl space-y-6">
            <div className="grid gap-2">
                <Label>{t('Pin your location')}</Label>
                <AddressPinPicker value={data.lat !== null && data.lng !== null ? { lat: data.lat, lng: data.lng } : null} onChange={onPin} />
                <InputError message={errors.lat ?? errors.lng} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="label">{t('Save as')}</Label>
                    <Select value={data.label} onValueChange={(value) => setData('label', value as AddressLabel)}>
                        <SelectTrigger id="label">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {labelValues.map((value) => (
                                <SelectItem key={value} value={value}>
                                    {labelNames[value]}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.label} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="postal_code">{t('Postal code')}</Label>
                    <Input id="postal_code" value={data.postal_code} onChange={(e) => setData('postal_code', e.target.value)} required />
                    <InputError message={errors.postal_code} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="line1">{t('House / flat, street')}</Label>
                <Input id="line1" value={data.line1} onChange={(e) => setData('line1', e.target.value)} required />
                <InputError message={errors.line1} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="line2">{t('Landmark or area (optional)')}</Label>
                <Input id="line2" value={data.line2} onChange={(e) => setData('line2', e.target.value)} />
                <InputError message={errors.line2} />
            </div>

            <div className="grid gap-2 sm:max-w-xs">
                <Label htmlFor="city">{t('City')}</Label>
                <Input id="city" value={data.city} onChange={(e) => setData('city', e.target.value)} required />
                <InputError message={errors.city} />
            </div>

            <div className="flex items-center gap-3">
                <Switch id="is_default" checked={data.is_default} onCheckedChange={(checked) => setData('is_default', checked)} />
                <Label htmlFor="is_default">{t('Use as my default address')}</Label>
            </div>

            <div className="flex gap-2">
                <Button type="submit" disabled={processing}>
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    {isEdit ? t('Save changes') : t('Save address')}
                </Button>
                <Button asChild variant="outline">
                    <Link href={route('addresses.index')}>{t('Cancel')}</Link>
                </Button>
            </div>
        </form>
    );
}
