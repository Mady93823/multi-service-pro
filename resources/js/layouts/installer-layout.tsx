import { useTrans } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';

interface InstallerLayoutProps {
    step: number; // 0-based index into steps
    children: React.ReactNode;
}

export default function InstallerLayout({ step, children }: InstallerLayoutProps) {
    const { name } = usePage<SharedData>().props;
    const t = useTrans();

    const steps = [t('Requirements'), t('Database'), t('Migrate'), t('Admin'), t('Done')];

    return (
        <div className="bg-background flex min-h-svh flex-col items-center gap-8 p-6 md:justify-center md:p-10">
            <div className="text-center">
                <h1 className="text-2xl font-semibold">{name}</h1>
                <p className="text-muted-foreground mt-1 text-sm">{t('Setup wizard')}</p>
            </div>

            <ol className="flex items-center gap-2 text-sm">
                {steps.map((label, index) => (
                    <li key={label} className="flex items-center gap-2">
                        <span
                            className={cn(
                                'flex h-6 w-6 items-center justify-center rounded-full border text-xs font-medium',
                                index < step && 'bg-primary text-primary-foreground border-primary',
                                index === step && 'border-primary text-primary',
                                index > step && 'text-muted-foreground',
                            )}
                        >
                            {index < step ? <Check className="h-3.5 w-3.5" /> : index + 1}
                        </span>
                        <span className={cn('hidden sm:inline', index === step ? 'font-medium' : 'text-muted-foreground')}>{label}</span>
                        {index < steps.length - 1 && <span className="text-muted-foreground">—</span>}
                    </li>
                ))}
            </ol>

            <div className="w-full max-w-xl">{children}</div>
        </div>
    );
}
