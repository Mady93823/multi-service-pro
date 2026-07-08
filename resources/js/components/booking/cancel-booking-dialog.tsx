import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { router } from '@inertiajs/react';
import { useState } from 'react';

interface CancelBookingDialogProps {
    bookingId: number;
    feePreview: string | null;
}

export function CancelBookingDialog({ bookingId, feePreview }: CancelBookingDialogProps) {
    const t = useTrans();
    const money = useMoney();
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);

    const hasFee = feePreview !== null && Number(feePreview) > 0;

    const confirm = () => {
        router.post(
            route('bookings.cancel', bookingId),
            { reason: reason === '' ? null : reason },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setOpen(false);
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" className="text-destructive">
                    {t('Cancel booking')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Cancel this booking?')}</DialogTitle>
                    <DialogDescription>
                        {hasFee
                            ? t('A cancellation fee of :amount will apply because the visit is close.', { amount: money(feePreview ?? '0') })
                            : t('You can cancel free of charge.')}
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-2">
                    <Label htmlFor="cancel-reason">{t('Reason (optional)')}</Label>
                    <Textarea id="cancel-reason" value={reason} onChange={(e) => setReason(e.target.value)} rows={3} maxLength={500} />
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        {t('Keep booking')}
                    </Button>
                    <Button variant="destructive" onClick={confirm} disabled={processing}>
                        {t('Cancel booking')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
