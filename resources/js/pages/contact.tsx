import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useRecaptcha } from '@/hooks/use-recaptcha';
import PublicLayout from '@/layouts/public-layout';
import { useTrans } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import { Mail, MapPin, Phone } from 'lucide-react';
import { FormEventHandler } from 'react';

interface ContactProps {
    contact: { email: string | null; phone: string | null; address: string | null };
    prefill: { name: string; email: string };
}

/**
 * Public contact form (M19). A submission opens a support ticket — signed in or
 * not — so there is one inbox, not two.
 */
export default function Contact({ contact, prefill }: ContactProps) {
    const t = useTrans();

    const recaptcha = useRecaptcha('contact');

    const { data, setData, post, transform, processing, errors, reset } = useForm({
        name: prefill.name,
        email: prefill.email,
        subject: '',
        message: '',
        website: '',
    });

    const submit: FormEventHandler = async (e) => {
        e.preventDefault();

        const token = await recaptcha();
        transform((current) => ({ ...current, recaptcha_token: token }));

        post(route('contact.store'), {
            preserveScroll: true,
            onSuccess: () => reset('subject', 'message'),
        });
    };

    return (
        <PublicLayout>
            <Head title={t('Contact us')} />

            <div className="grid gap-8 md:grid-cols-3">
                <div className="space-y-4">
                    <h1 className="text-2xl font-semibold">{t('Contact us')}</h1>
                    <p className="text-muted-foreground text-sm">{t('Send us a message and our team will get back to you.')}</p>

                    <ul className="text-muted-foreground space-y-2 text-sm">
                        {contact.email !== null && (
                            <li className="flex items-start gap-2">
                                <Mail className="mt-0.5 h-4 w-4 shrink-0" />
                                <a href={`mailto:${contact.email}`} className="hover:text-foreground">
                                    {contact.email}
                                </a>
                            </li>
                        )}
                        {contact.phone !== null && (
                            <li className="flex items-start gap-2">
                                <Phone className="mt-0.5 h-4 w-4 shrink-0" />
                                <a href={`tel:${contact.phone}`} className="hover:text-foreground">
                                    {contact.phone}
                                </a>
                            </li>
                        )}
                        {contact.address !== null && (
                            <li className="flex items-start gap-2">
                                <MapPin className="mt-0.5 h-4 w-4 shrink-0" />
                                <span>{contact.address}</span>
                            </li>
                        )}
                    </ul>
                </div>

                <Card className="md:col-span-2">
                    <CardHeader>
                        <CardTitle>{t('Send a message')}</CardTitle>
                        <CardDescription>{t('We answer through the help centre, so nothing gets lost.')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">{t('Your name')}</Label>
                                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">{t('Email address')}</Label>
                                    <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
                                    <InputError message={errors.email} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="subject">{t('Subject')}</Label>
                                <Input id="subject" value={data.subject} onChange={(e) => setData('subject', e.target.value)} required />
                                <InputError message={errors.subject} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="message">{t('Message')}</Label>
                                <Textarea id="message" value={data.message} onChange={(e) => setData('message', e.target.value)} rows={6} required />
                                <InputError message={errors.message} />
                            </div>

                            {/* Honeypot: hidden from people, rejected when filled. */}
                            <input
                                type="text"
                                tabIndex={-1}
                                autoComplete="off"
                                aria-hidden="true"
                                className="hidden"
                                value={data.website}
                                onChange={(e) => setData('website', e.target.value)}
                            />

                            <Button type="submit" disabled={processing}>
                                {t('Send message')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </PublicLayout>
    );
}
