import { SlotPicker } from '@/components/booking/slot-picker';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { useTrans } from '@/lib/i18n';
import { type SlotDay } from '@/types';
import { router } from '@inertiajs/react';
import { useState } from 'react';

interface RescheduleDialogProps {
    bookingId: number;
    days: SlotDay[];
}

export function RescheduleDialog({ bookingId, days }: RescheduleDialogProps) {
    const t = useTrans();
    const [open, setOpen] = useState(false);
    const [slot, setSlot] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const confirm = () => {
        if (slot === null) {
            return;
        }

        router.post(
            route('bookings.reschedule', bookingId),
            { scheduled_at: slot },
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
                <Button variant="outline">{t('Reschedule')}</Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{t('Pick a new time')}</DialogTitle>
                    <DialogDescription>{t('If a professional was already assigned, we will find one for the new slot.')}</DialogDescription>
                </DialogHeader>
                <SlotPicker days={days} value={slot} onChange={setSlot} />
                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        {t('Never mind')}
                    </Button>
                    <Button onClick={confirm} disabled={slot === null || processing}>
                        {t('Reschedule')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
