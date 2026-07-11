import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import CustomerLayout from '@/layouts/customer-layout';
import ProviderLayout from '@/layouts/provider-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Paperclip } from 'lucide-react';
import { type ComponentType } from 'react';

interface BookingOption {
    id: number;
    code: string;
    created_at: string | null;
}

interface SupportCreateProps {
    booking_id: number | null;
    bookings: BookingOption[];
}

interface LayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

function layoutForRoles(roles: string[]): ComponentType<LayoutProps> {
    return roles.includes('provider') ? ProviderLayout : CustomerLayout;
}

export default function SupportCreate({ booking_id, bookings }: SupportCreateProps) {
    const t = useTrans();
    const { auth } = usePage<SharedData>().props;
    const Layout = layoutForRoles(auth.roles);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Help & support'), href: '/support/tickets' },
        { title: t('New ticket'), href: '/support/tickets/create' },
    ];

    const categories = [
        { value: 'booking', label: t('Booking issue') },
        { value: 'payment', label: t('Payment & refunds') },
        { value: 'account', label: t('Account') },
        { value: 'other', label: t('Something else') },
    ];

    const priorities = [
        { value: 'low', label: t('Low') },
        { value: 'normal', label: t('Normal') },
        { value: 'high', label: t('High') },
    ];

    const form = useForm<{
        subject: string;
        category: string;
        priority: string;
        booking_id: string;
        message: string;
        attachments: File[];
    }>({
        subject: '',
        category: booking_id !== null ? 'booking' : 'other',
        priority: 'normal',
        booking_id: booking_id !== null ? String(booking_id) : '',
        message: '',
        attachments: [],
    });

    const attachmentError = form.errors.attachments ?? Object.entries(form.errors).find(([key]) => key.startsWith('attachments.'))?.[1];

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            booking_id: data.booking_id === '' ? null : Number(data.booking_id),
        }));
        form.post(route('support.tickets.store'), { forceFormData: true });
    };

    return (
        <Layout breadcrumbs={breadcrumbs}>
            <Head title={t('New ticket')} />
            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>{t('Raise a support ticket')}</CardTitle>
                        <CardDescription>{t('Tell us what went wrong — our team replies as soon as possible.')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="subject">{t('Subject')}</Label>
                                <Input
                                    id="subject"
                                    value={form.data.subject}
                                    maxLength={150}
                                    onChange={(event) => form.setData('subject', event.target.value)}
                                />
                                <InputError message={form.errors.subject} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>{t('Category')}</Label>
                                    <Select value={form.data.category} onValueChange={(value) => form.setData('category', value)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {categories.map((category) => (
                                                <SelectItem key={category.value} value={category.value}>
                                                    {category.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={form.errors.category} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>{t('Priority')}</Label>
                                    <Select value={form.data.priority} onValueChange={(value) => form.setData('priority', value)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {priorities.map((priority) => (
                                                <SelectItem key={priority.value} value={priority.value}>
                                                    {priority.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={form.errors.priority} />
                                </div>
                            </div>

                            {bookings.length > 0 && (
                                <div className="grid gap-2">
                                    <Label>{t('Related booking (optional)')}</Label>
                                    <Select
                                        value={form.data.booking_id === '' ? 'none' : form.data.booking_id}
                                        onValueChange={(value) => form.setData('booking_id', value === 'none' ? '' : value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder={t('No booking')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">{t('No booking')}</SelectItem>
                                            {bookings.map((booking) => (
                                                <SelectItem key={booking.id} value={String(booking.id)}>
                                                    {booking.code}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={form.errors.booking_id} />
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="message">{t('Describe the issue')}</Label>
                                <Textarea
                                    id="message"
                                    rows={5}
                                    value={form.data.message}
                                    onChange={(event) => form.setData('message', event.target.value)}
                                />
                                <InputError message={form.errors.message} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="attachments" className="flex items-center gap-1">
                                    <Paperclip className="h-4 w-4" />
                                    {t('Attachments (optional)')}
                                </Label>
                                <input
                                    id="attachments"
                                    type="file"
                                    multiple
                                    accept=".jpg,.jpeg,.png,.webp,.pdf"
                                    className="text-sm"
                                    onChange={(event) => form.setData('attachments', Array.from(event.target.files ?? []))}
                                />
                                <InputError message={attachmentError} />
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={form.processing}>
                                    {t('Create ticket')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </Layout>
    );
}
