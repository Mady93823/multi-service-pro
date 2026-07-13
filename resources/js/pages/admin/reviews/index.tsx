import InputError from '@/components/input-error';
import { StarRating } from '@/components/reviews/star-rating';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type Paginated, type Review } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Eye, EyeOff, Quote } from 'lucide-react';
import { useState } from 'react';

interface AdminReviewsProps {
    reviews: Paginated<Review>;
    filters: { visibility: string; rating: number | null };
}

export default function AdminReviews({ reviews, filters }: AdminReviewsProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Reviews'), href: '/admin/reviews' },
    ];

    const dateFormat = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' });

    const applyFilters = (visibility: string, rating: number | null) => {
        router.get(
            route('admin.reviews.index'),
            {
                ...(visibility === '' ? {} : { visibility }),
                ...(rating === null ? {} : { rating }),
            },
            { preserveState: true, replace: true },
        );
    };

    const visibilityOptions: { value: string; label: string }[] = [
        { value: '', label: t('All') },
        { value: 'visible', label: t('Visible') },
        { value: 'hidden', label: t('Hidden') },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Reviews')} />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold">{t('Reviews')}</h1>
                    <div className="flex flex-wrap items-center gap-1">
                        {visibilityOptions.map((option) => (
                            <Button
                                key={option.value}
                                size="sm"
                                variant={filters.visibility === option.value ? 'default' : 'outline'}
                                onClick={() => applyFilters(option.value, filters.rating)}
                            >
                                {option.label}
                            </Button>
                        ))}
                        <span className="text-muted-foreground mx-1 text-xs">{t('Stars')}</span>
                        {[5, 4, 3, 2, 1].map((stars) => (
                            <Button
                                key={stars}
                                size="sm"
                                variant={filters.rating === stars ? 'default' : 'outline'}
                                onClick={() => applyFilters(filters.visibility, filters.rating === stars ? null : stars)}
                            >
                                {stars}★
                            </Button>
                        ))}
                    </div>
                </div>

                <Card>
                    <CardContent className="p-0">
                        {reviews.data.length === 0 ? (
                            <p className="text-muted-foreground p-6 text-center text-sm">{t('No reviews to show.')}</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Rating')}</TableHead>
                                        <TableHead>{t('Review')}</TableHead>
                                        <TableHead>{t('Booking')}</TableHead>
                                        <TableHead>{t('Status')}</TableHead>
                                        <TableHead className="text-right">{t('Actions')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {reviews.data.map((review) => (
                                        <TableRow key={review.id}>
                                            <TableCell className="align-top">
                                                <StarRating rating={review.rating} />
                                                <span className="text-muted-foreground block text-xs">
                                                    {review.created_at !== null && dateFormat.format(new Date(review.created_at))}
                                                </span>
                                            </TableCell>
                                            <TableCell className="max-w-md align-top">
                                                <span className="font-medium">{review.customer_name ?? '—'}</span>
                                                {review.comment !== null && review.comment !== '' && (
                                                    <span className="text-muted-foreground line-clamp-2 block text-sm">{review.comment}</span>
                                                )}
                                                {review.photo_urls !== undefined && review.photo_urls.length > 0 && (
                                                    <span className="mt-1 flex flex-wrap gap-1">
                                                        {review.photo_urls.map((url) => (
                                                            <a key={url} href={url} target="_blank" rel="noreferrer">
                                                                <img src={url} alt="" className="h-10 w-10 rounded border object-cover" />
                                                            </a>
                                                        ))}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="align-top text-sm">
                                                {review.booking_code ?? '—'}
                                                <span className="text-muted-foreground block text-xs">{review.provider_name ?? ''}</span>
                                            </TableCell>
                                            <TableCell className="align-top text-sm">
                                                {review.is_hidden ? (
                                                    <>
                                                        <span className="text-red-700 dark:text-red-400">{t('Hidden')}</span>
                                                        {review.hidden_reason !== null && (
                                                            <span className="text-muted-foreground block text-xs">{review.hidden_reason}</span>
                                                        )}
                                                    </>
                                                ) : (
                                                    <span className="text-emerald-700 dark:text-emerald-400">{t('Visible')}</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right align-top">
                                                <ReviewActions review={review} />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {(reviews.links.prev !== null || reviews.links.next !== null) && (
                    <div className="flex items-center justify-between text-sm">
                        {reviews.links.prev !== null ? (
                            <Link href={reviews.links.prev} preserveScroll className="text-primary hover:underline">
                                {t('Previous')}
                            </Link>
                        ) : (
                            <span />
                        )}
                        {reviews.links.next !== null && (
                            <Link href={reviews.links.next} preserveScroll className="text-primary hover:underline">
                                {t('Next')}
                            </Link>
                        )}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}

function ReviewActions({ review }: { review: Review }) {
    const t = useTrans();
    const [hideOpen, setHideOpen] = useState(false);

    const hide = useForm({ reason: '' });

    if (review.is_hidden) {
        return (
            <Button size="sm" variant="outline" onClick={() => router.post(route('admin.reviews.unhide', review.id), {}, { preserveScroll: true })}>
                <Eye className="mr-1 h-4 w-4" />
                {t('Restore')}
            </Button>
        );
    }

    return (
        <>
            {/* M19: a good review becomes storefront copy in one click. */}
            <Button size="sm" variant="ghost" onClick={() => router.post(route('admin.reviews.promote', review.id), {}, { preserveScroll: true })}>
                <Quote className="mr-1 h-4 w-4" />
                {t('Use as testimonial')}
            </Button>

            <Dialog open={hideOpen} onOpenChange={setHideOpen}>
                <DialogTrigger asChild>
                    <Button size="sm" variant="outline">
                        <EyeOff className="mr-1 h-4 w-4" />
                        {t('Hide')}
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Hide this review?')}</DialogTitle>
                        <DialogDescription>
                            {t('It disappears from the storefront and stops counting toward the professional’s rating. The customer keeps it.')}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor={`hide-reason-${review.id}`}>{t('Reason')}</Label>
                        <Textarea
                            id={`hide-reason-${review.id}`}
                            value={hide.data.reason}
                            onChange={(event) => hide.setData('reason', event.target.value)}
                        />
                        <InputError message={hide.errors.reason} />
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setHideOpen(false)} disabled={hide.processing}>
                            {t('Cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={hide.processing}
                            onClick={() =>
                                hide.post(route('admin.reviews.hide', review.id), {
                                    preserveScroll: true,
                                    onSuccess: () => setHideOpen(false),
                                })
                            }
                        >
                            {t('Hide review')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
