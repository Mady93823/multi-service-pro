import { StarRating } from '@/components/reviews/star-rating';
import { useTrans } from '@/lib/i18n';
import type { Review } from '@/types';

/**
 * One review as the storefront / booking page / provider dashboard shows it.
 * Photos come through the policy-checked photo route — never a raw disk URL.
 */
export function ReviewCard({ review, title }: { review: Review; title?: string }) {
    const t = useTrans();
    const dateFormat = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' });

    return (
        <div className="space-y-2 rounded-lg border p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                    <StarRating rating={review.rating} />
                    <span className="text-sm font-medium">{title ?? review.customer_name ?? t('Customer')}</span>
                </div>
                <span className="text-muted-foreground text-xs">{review.created_at !== null && dateFormat.format(new Date(review.created_at))}</span>
            </div>
            {review.comment !== null && review.comment !== '' && <p className="text-sm leading-relaxed">{review.comment}</p>}
            {review.photo_urls !== undefined && review.photo_urls.length > 0 && (
                <div className="flex flex-wrap gap-2 pt-1">
                    {review.photo_urls.map((url) => (
                        <a key={url} href={url} target="_blank" rel="noreferrer">
                            <img
                                src={url}
                                alt={t('Review photo')}
                                className="h-16 w-16 rounded-md border object-cover transition-opacity hover:opacity-80"
                            />
                        </a>
                    ))}
                </div>
            )}
        </div>
    );
}
