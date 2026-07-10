import InputError from '@/components/input-error';
import { StarInput } from '@/components/reviews/star-rating';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { ImagePlus } from 'lucide-react';

/**
 * The one-shot review form on a completed booking. Rating is required;
 * comment and photos are optional. maxPhotos comes from settings
 * (reviews.max_photos) — 0 hides the photo picker entirely.
 */
export function ReviewForm({ bookingId, maxPhotos }: { bookingId: number; maxPhotos: number }) {
    const t = useTrans();

    const { data, setData, post, processing, errors } = useForm<{
        rating: number;
        comment: string;
        photos: File[];
    }>({
        rating: 0,
        comment: '',
        photos: [],
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post(route('bookings.review.store', bookingId), { preserveScroll: true, forceFormData: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{t('Rate your service')}</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label>{t('Your rating')}</Label>
                        <StarInput value={data.rating} onChange={(rating) => setData('rating', rating)} />
                        <InputError message={errors.rating} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="review-comment">{t('Your review (optional)')}</Label>
                        <Textarea
                            id="review-comment"
                            value={data.comment}
                            maxLength={2000}
                            rows={3}
                            placeholder={t('How was the service?')}
                            onChange={(event) => setData('comment', event.target.value)}
                        />
                        <InputError message={errors.comment} />
                    </div>

                    {maxPhotos > 0 && (
                        <div className="grid gap-2">
                            <Label htmlFor="review-photos" className="flex items-center gap-1">
                                <ImagePlus className="h-4 w-4" />
                                {t('Photos (optional, up to :max)', { max: String(maxPhotos) })}
                            </Label>
                            <input
                                id="review-photos"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                multiple
                                className="text-sm"
                                onChange={(event) => setData('photos', Array.from(event.target.files ?? []).slice(0, maxPhotos))}
                            />
                            {data.photos.length > 0 && (
                                <p className="text-muted-foreground text-xs">
                                    {t(':count photo(s) selected', { count: String(data.photos.length) })}
                                </p>
                            )}
                            <InputError message={errors.photos} />
                        </div>
                    )}

                    <Button type="submit" disabled={processing || data.rating === 0}>
                        {t('Submit review')}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}
