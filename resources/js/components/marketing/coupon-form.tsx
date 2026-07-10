import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useTrans } from '@/lib/i18n';
import { type Coupon } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

type CouponFormData = {
    code: string;
    type: 'flat' | 'percent';
    value: string;
    max_discount: string;
    min_order: string;
    usage_limit: string;
    per_user_limit: string;
    first_order_only: boolean;
    starts_at: string;
    ends_at: string;
    is_active: boolean;
};

interface CouponFormProps {
    coupon?: Coupon;
}

export function CouponForm({ coupon }: CouponFormProps) {
    const isEdit = coupon !== undefined;
    const t = useTrans();

    const { data, setData, put, post, processing, errors, transform } = useForm<CouponFormData>({
        code: coupon?.code ?? '',
        type: coupon?.type ?? 'flat',
        value: coupon?.value ?? '',
        max_discount: coupon?.max_discount ?? '',
        min_order: coupon?.min_order ?? '',
        usage_limit: coupon?.usage_limit?.toString() ?? '',
        per_user_limit: coupon?.per_user_limit?.toString() ?? '',
        first_order_only: coupon?.first_order_only ?? false,
        starts_at: coupon?.starts_at ?? '',
        ends_at: coupon?.ends_at ?? '',
        is_active: coupon?.is_active ?? true,
    });

    transform((current) => ({
        ...current,
        max_discount: current.type === 'percent' && current.max_discount !== '' ? current.max_discount : null,
        min_order: current.min_order !== '' ? current.min_order : null,
        usage_limit: current.usage_limit !== '' ? current.usage_limit : null,
        per_user_limit: current.per_user_limit !== '' ? current.per_user_limit : null,
        starts_at: current.starts_at !== '' ? current.starts_at : null,
        ends_at: current.ends_at !== '' ? current.ends_at : null,
    }));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (isEdit) {
            put(route('admin.coupons.update', coupon.id));
        } else {
            post(route('admin.coupons.store'));
        }
    };

    return (
        <form onSubmit={submit} className="max-w-xl space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="code">{t('Code')}</Label>
                <Input
                    id="code"
                    value={data.code}
                    onChange={(e) => setData('code', e.target.value.toUpperCase())}
                    placeholder="WELCOME10"
                    required
                    autoFocus
                />
                <InputError message={errors.code} />
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="type">{t('Discount type')}</Label>
                    <Select value={data.type} onValueChange={(value) => setData('type', value as 'flat' | 'percent')}>
                        <SelectTrigger id="type">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="flat">{t('Flat amount')}</SelectItem>
                            <SelectItem value="percent">{t('Percentage')}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.type} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="value">{data.type === 'percent' ? t('Discount (%)') : t('Discount amount')}</Label>
                    <Input
                        id="value"
                        type="number"
                        min={0.01}
                        max={data.type === 'percent' ? 100 : undefined}
                        step="0.01"
                        value={data.value}
                        onChange={(e) => setData('value', e.target.value)}
                        required
                    />
                    <InputError message={errors.value} />
                </div>
            </div>

            {data.type === 'percent' && (
                <div className="grid gap-2">
                    <Label htmlFor="max_discount">{t('Maximum discount')}</Label>
                    <Input
                        id="max_discount"
                        type="number"
                        min={0.01}
                        step="0.01"
                        value={data.max_discount}
                        onChange={(e) => setData('max_discount', e.target.value)}
                        placeholder={t('No cap')}
                    />
                    <InputError message={errors.max_discount} />
                </div>
            )}

            <div className="grid gap-2">
                <Label htmlFor="min_order">{t('Minimum order value')}</Label>
                <Input
                    id="min_order"
                    type="number"
                    min={0}
                    step="0.01"
                    value={data.min_order}
                    onChange={(e) => setData('min_order', e.target.value)}
                    placeholder={t('No minimum')}
                />
                <InputError message={errors.min_order} />
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="usage_limit">{t('Total usage limit')}</Label>
                    <Input
                        id="usage_limit"
                        type="number"
                        min={1}
                        value={data.usage_limit}
                        onChange={(e) => setData('usage_limit', e.target.value)}
                        placeholder={t('Unlimited')}
                    />
                    <InputError message={errors.usage_limit} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="per_user_limit">{t('Per-customer limit')}</Label>
                    <Input
                        id="per_user_limit"
                        type="number"
                        min={1}
                        value={data.per_user_limit}
                        onChange={(e) => setData('per_user_limit', e.target.value)}
                        placeholder={t('Unlimited')}
                    />
                    <InputError message={errors.per_user_limit} />
                </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="starts_at">{t('Starts')}</Label>
                    <Input id="starts_at" type="datetime-local" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} />
                    <InputError message={errors.starts_at} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="ends_at">{t('Ends')}</Label>
                    <Input id="ends_at" type="datetime-local" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} />
                    <InputError message={errors.ends_at} />
                </div>
            </div>

            <div className="flex items-center gap-3">
                <Switch id="first_order_only" checked={data.first_order_only} onCheckedChange={(checked) => setData('first_order_only', checked)} />
                <Label htmlFor="first_order_only">{t('First order only')}</Label>
            </div>

            <div className="flex items-center gap-3">
                <Switch id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
                <Label htmlFor="is_active">{t('Active')}</Label>
            </div>

            <div className="flex gap-2">
                <Button type="submit" disabled={processing}>
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    {isEdit ? t('Save changes') : t('Create coupon')}
                </Button>
                <Button asChild variant="outline">
                    <Link href={route('admin.coupons.index')}>{t('Cancel')}</Link>
                </Button>
            </div>
        </form>
    );
}
