import InputError from '@/components/input-error';
import { MediaPicker } from '@/components/media/media-picker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BankAccount, type BreadcrumbItem, type MediaAsset } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface BankAccountsIndexProps {
    accounts: BankAccount[];
    offline_enabled: boolean;
}

export default function BankAccountsIndex({ accounts, offline_enabled: offlineEnabled }: BankAccountsIndexProps) {
    const t = useTrans();
    const [editing, setEditing] = useState<BankAccount | null | undefined>(undefined);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Payments'), href: '/admin/payments' },
        { title: t('Bank accounts'), href: '/admin/bank-accounts' },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Bank accounts')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{t('Bank accounts')}</h1>
                    <Button onClick={() => setEditing(null)}>
                        <Plus className="h-4 w-4" />
                        {t('Add account')}
                    </Button>
                </div>
                <p className="text-muted-foreground text-sm">
                    {t('Shown at checkout when a customer chooses to pay by bank transfer. Their payment waits for you to verify it.')}
                </p>

                {!offlineEnabled && accounts.length > 0 && (
                    <p className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm">
                        <AlertTriangle className="h-4 w-4 shrink-0" />
                        {t('Bank transfer is switched off, so customers are not offered these accounts.')}
                        <Link href={route('admin.settings.edit', 'payments')} className="underline">
                            {t('Payment settings')}
                        </Link>
                    </p>
                )}

                {accounts.length === 0 ? (
                    <div className="text-muted-foreground rounded-xl border border-dashed py-16 text-center text-sm">
                        {t('No bank accounts yet.')}
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {accounts.map((account) => (
                            <Card key={account.id}>
                                <CardContent className="space-y-3 pt-6">
                                    <div className="flex items-start justify-between gap-2">
                                        <p className="font-medium">{account.label}</p>
                                        {account.is_active ? (
                                            <Badge variant="secondary">{t('Live')}</Badge>
                                        ) : (
                                            <Badge variant="outline">{t('Hidden')}</Badge>
                                        )}
                                    </div>

                                    <dl className="text-muted-foreground space-y-1 text-xs">
                                        {account.account_number !== null && (
                                            <div className="flex justify-between gap-2">
                                                <dt>{t('Account')}</dt>
                                                <dd className="truncate">{account.account_number}</dd>
                                            </div>
                                        )}
                                        {account.ifsc !== null && (
                                            <div className="flex justify-between gap-2">
                                                <dt>{t('IFSC')}</dt>
                                                <dd>{account.ifsc}</dd>
                                            </div>
                                        )}
                                        {account.upi_id !== null && (
                                            <div className="flex justify-between gap-2">
                                                <dt>{t('UPI')}</dt>
                                                <dd className="truncate">{account.upi_id}</dd>
                                            </div>
                                        )}
                                    </dl>

                                    {account.qr_thumb_url !== null && (
                                        <img src={account.qr_thumb_url} alt="" className="h-24 w-24 rounded-md border object-contain" />
                                    )}

                                    <div className="flex justify-end gap-1">
                                        <Button variant="ghost" size="icon" aria-label={t('Edit')} onClick={() => setEditing(account)}>
                                            <Pencil className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Delete')}
                                            onClick={() => router.delete(route('admin.bank-accounts.destroy', account.id), { preserveScroll: true })}
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

            {editing !== undefined && <BankAccountDialog key={editing?.id ?? 'new'} account={editing} onClose={() => setEditing(undefined)} />}
        </AdminLayout>
    );
}

function BankAccountDialog({ account, onClose }: { account: BankAccount | null; onClose: () => void }) {
    const t = useTrans();
    const [asset, setAsset] = useState<MediaAsset | null>(null);

    const { data, setData, post, put, processing, errors } = useForm({
        label: account?.label ?? '',
        account_name: account?.account_name ?? '',
        account_number: account?.account_number ?? '',
        ifsc: account?.ifsc ?? '',
        upi_id: account?.upi_id ?? '',
        notes: account?.notes ?? '',
        is_active: account?.is_active ?? true,
        sort_order: account?.sort_order ?? 0,
        media_asset_id: null as number | null,
    });

    const pick = (picked: MediaAsset | null) => {
        setAsset(picked);
        setData('media_asset_id', picked?.id ?? null);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onClose() };

        if (account === null) {
            post(route('admin.bank-accounts.store'), options);
        } else {
            put(route('admin.bank-accounts.update', account.id), options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{account === null ? t('Add account') : t('Edit account')}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="label">{t('Label')}</Label>
                        <Input
                            id="label"
                            value={data.label}
                            onChange={(e) => setData('label', e.target.value)}
                            placeholder={t('Company current account')}
                            required
                        />
                        <InputError message={errors.label} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="account_name">{t('Account holder')}</Label>
                        <Input id="account_name" value={data.account_name} onChange={(e) => setData('account_name', e.target.value)} />
                        <InputError message={errors.account_name} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="account_number">{t('Account number')}</Label>
                            <Input id="account_number" value={data.account_number} onChange={(e) => setData('account_number', e.target.value)} />
                            <InputError message={errors.account_number} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ifsc">{t('IFSC')}</Label>
                            <Input id="ifsc" value={data.ifsc} onChange={(e) => setData('ifsc', e.target.value)} />
                            <InputError message={errors.ifsc} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="upi_id">{t('UPI id')}</Label>
                        <Input id="upi_id" value={data.upi_id} onChange={(e) => setData('upi_id', e.target.value)} placeholder="name@bank" />
                        <InputError message={errors.upi_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label>{t('Payment QR')}</Label>
                        <MediaPicker value={asset} onChange={pick} currentUrl={account?.qr_url ?? null} error={errors.media_asset_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="notes">{t('Instructions')}</Label>
                        <Textarea
                            id="notes"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            placeholder={t('Add your booking code as the transfer remark.')}
                        />
                        <InputError message={errors.notes} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="sort_order">{t('Sort order')}</Label>
                        <Input
                            id="sort_order"
                            type="number"
                            min={0}
                            value={data.sort_order}
                            onChange={(e) => setData('sort_order', Number(e.target.value))}
                            className="w-32"
                        />
                        <InputError message={errors.sort_order} />
                    </div>

                    <label className="flex items-center justify-between gap-4 text-sm">
                        <span className="font-medium">{t('Offer at checkout')}</span>
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
