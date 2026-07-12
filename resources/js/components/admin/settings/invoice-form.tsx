import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface InvoiceValues {
    invoice_prefix: string;
    invoice_company_name: string | null;
    invoice_gstin: string | null;
    invoice_address: string | null;
    invoice_state: string | null;
}

export default function InvoiceForm({ values }: { values: InvoiceValues }) {
    const t = useTrans();

    const { data, setData, put, processing, errors } = useForm({
        invoice_prefix: values.invoice_prefix,
        invoice_company_name: values.invoice_company_name ?? '',
        invoice_gstin: values.invoice_gstin ?? '',
        invoice_address: values.invoice_address ?? '',
        invoice_state: values.invoice_state ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'invoice'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="invoice_prefix">{t('Invoice number prefix')}</Label>
                    <Input id="invoice_prefix" value={data.invoice_prefix} onChange={(e) => setData('invoice_prefix', e.target.value)} required />
                    <InputError message={errors.invoice_prefix} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="invoice_company_name">{t('Company name')}</Label>
                    <Input
                        id="invoice_company_name"
                        value={data.invoice_company_name}
                        onChange={(e) => setData('invoice_company_name', e.target.value)}
                    />
                    <InputError message={errors.invoice_company_name} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="invoice_gstin">{t('GSTIN')}</Label>
                    <Input
                        id="invoice_gstin"
                        value={data.invoice_gstin}
                        onChange={(e) => setData('invoice_gstin', e.target.value)}
                        placeholder="22AAAAA0000A1Z5"
                    />
                    <InputError message={errors.invoice_gstin} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="invoice_state">{t('State')}</Label>
                    <Input id="invoice_state" value={data.invoice_state} onChange={(e) => setData('invoice_state', e.target.value)} />
                    <InputError message={errors.invoice_state} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="invoice_address">{t('Registered address')}</Label>
                <Input id="invoice_address" value={data.invoice_address} onChange={(e) => setData('invoice_address', e.target.value)} />
                <InputError message={errors.invoice_address} />
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}
