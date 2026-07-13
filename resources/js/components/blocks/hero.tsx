import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

export interface HeroProps {
    heading: string;
    subheading: string | null;
    image_url: string | null;
    show_search: boolean;
    cta_label: string | null;
    cta_url: string | null;
    align: string;
}

export function HeroBlock({ heading, subheading, image_url, show_search, cta_label, cta_url, align }: HeroProps) {
    const t = useTrans();
    const [term, setTerm] = useState('');
    const centered = align !== 'left';

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('catalog.index'), term !== '' ? { search: term } : {});
    };

    return (
        <section
            className={cn(
                'relative overflow-hidden rounded-2xl px-6 py-14',
                image_url !== null ? 'text-white' : 'bg-muted/40',
                centered ? 'text-center' : 'text-left',
            )}
        >
            {image_url !== null && (
                <>
                    <img src={image_url} alt="" className="absolute inset-0 h-full w-full object-cover" />
                    <div className="absolute inset-0 bg-black/50" />
                </>
            )}

            <div className={cn('relative mx-auto max-w-2xl', centered ? 'mx-auto' : 'ml-0')}>
                <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">{heading}</h1>
                {subheading !== null && <p className={cn('mt-3', image_url !== null ? 'text-white/85' : 'text-muted-foreground')}>{subheading}</p>}

                {show_search && (
                    <form onSubmit={submit} className="mt-6 flex gap-2">
                        <Input
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder={t('What do you need help with?')}
                            className={cn('h-11', image_url !== null && 'bg-white text-neutral-900')}
                        />
                        <Button type="submit" className="h-11">
                            <Search className="h-4 w-4" />
                            {t('Search')}
                        </Button>
                    </form>
                )}

                {cta_label !== null && cta_url !== null && (
                    <Button asChild size="lg" className="mt-6">
                        <a href={cta_url}>{cta_label}</a>
                    </Button>
                )}
            </div>
        </section>
    );
}
