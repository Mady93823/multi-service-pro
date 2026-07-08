import { Button } from '@/components/ui/button';
import { useTrans } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { type SlotDay } from '@/types';
import { useState } from 'react';

interface SlotPickerProps {
    days: SlotDay[];
    value: string | null;
    onChange: (value: string) => void;
}

/**
 * Day tabs + time-slot grid fed by SlotGenerator (settings-driven).
 */
export function SlotPicker({ days, value, onChange }: SlotPickerProps) {
    const t = useTrans();
    const selectedDay = days.find((day) => day.slots.some((slot) => slot.value === value));
    const [activeDate, setActiveDate] = useState<string>(selectedDay?.date ?? days[0]?.date ?? '');
    const active = days.find((day) => day.date === activeDate) ?? days[0];

    if (days.length === 0) {
        return <p className="text-muted-foreground text-sm">{t('No slots are open right now. Please check back soon.')}</p>;
    }

    return (
        <div className="space-y-3">
            <div className="flex gap-2 overflow-x-auto pb-1">
                {days.map((day) => (
                    <Button
                        key={day.date}
                        type="button"
                        variant={day.date === active?.date ? 'default' : 'outline'}
                        size="sm"
                        className="shrink-0"
                        onClick={() => setActiveDate(day.date)}
                    >
                        {day.label}
                    </Button>
                ))}
            </div>
            <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                {active?.slots.map((slot) => (
                    <Button
                        key={slot.value}
                        type="button"
                        variant={slot.value === value ? 'default' : 'outline'}
                        size="sm"
                        className={cn(slot.value === value && 'ring-primary ring-2 ring-offset-1')}
                        onClick={() => onChange(slot.value)}
                    >
                        {slot.label}
                    </Button>
                ))}
            </div>
        </div>
    );
}
