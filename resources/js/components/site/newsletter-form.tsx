import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/**
 * Footer newsletter signup (M19). The `website` field is a honeypot: it is
 * hidden from people and rejected by the server when filled.
 */
export function NewsletterForm() {
    const t = useTrans();
    const { data, setData, post, processing, errors, reset } = useForm({ email: '', website: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('newsletter.store'), { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <form onSubmit={submit} className="space-y-2">
            <p className="text-sm font-semibold">{t('Newsletter')}</p>
            <div className="flex gap-2">
                <Input
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    placeholder={t('you@example.com')}
                    aria-label={t('Email address')}
                    required
                />
                <Button type="submit" size="sm" disabled={processing}>
                    {t('Join')}
                </Button>
            </div>
            <input
                type="text"
                tabIndex={-1}
                autoComplete="off"
                aria-hidden="true"
                className="hidden"
                value={data.website}
                onChange={(e) => setData('website', e.target.value)}
            />
            {errors.email !== undefined && <p className="text-destructive text-xs">{errors.email}</p>}
        </form>
    );
}
