import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { useTrans } from '@/lib/i18n';
import { type WeekDayKey, type WorkingHours } from '@/types';

export const WEEK_DAYS: WeekDayKey[] = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

export function useDayLabels(): Record<WeekDayKey, string> {
    const t = useTrans();

    return {
        mon: t('Monday'),
        tue: t('Tuesday'),
        wed: t('Wednesday'),
        thu: t('Thursday'),
        fri: t('Friday'),
        sat: t('Saturday'),
        sun: t('Sunday'),
    };
}

export function defaultWorkingHours(): WorkingHours {
    return {
        mon: { off: false, start: '09:00', end: '18:00' },
        tue: { off: false, start: '09:00', end: '18:00' },
        wed: { off: false, start: '09:00', end: '18:00' },
        thu: { off: false, start: '09:00', end: '18:00' },
        fri: { off: false, start: '09:00', end: '18:00' },
        sat: { off: false, start: '09:00', end: '18:00' },
        sun: { off: true },
    };
}

interface WorkingHoursEditorProps {
    value: WorkingHours;
    onChange: (value: WorkingHours) => void;
}

export function WorkingHoursEditor({ value, onChange }: WorkingHoursEditorProps) {
    const t = useTrans();
    const dayLabels = useDayLabels();

    const setDay = (day: WeekDayKey, patch: Partial<WorkingHours[WeekDayKey]>) => {
        onChange({ ...value, [day]: { ...value[day], ...patch } });
    };

    return (
        <div className="space-y-2">
            {WEEK_DAYS.map((day) => {
                const entry = value[day];

                return (
                    <div key={day} className="flex items-center gap-3">
                        <span className="w-24 text-sm">{dayLabels[day]}</span>
                        <Switch
                            checked={!entry.off}
                            onCheckedChange={(checked) =>
                                setDay(day, checked ? { off: false, start: entry.start ?? '09:00', end: entry.end ?? '18:00' } : { off: true })
                            }
                            aria-label={dayLabels[day]}
                        />
                        {entry.off ? (
                            <span className="text-muted-foreground text-sm">{t('Day off')}</span>
                        ) : (
                            <div className="flex items-center gap-2">
                                <Input
                                    type="time"
                                    value={entry.start ?? ''}
                                    onChange={(e) => setDay(day, { start: e.target.value })}
                                    className="w-28"
                                    aria-label={t('Start time')}
                                />
                                <span className="text-muted-foreground text-sm">{t('to')}</span>
                                <Input
                                    type="time"
                                    value={entry.end ?? ''}
                                    onChange={(e) => setDay(day, { end: e.target.value })}
                                    className="w-28"
                                    aria-label={t('End time')}
                                />
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
