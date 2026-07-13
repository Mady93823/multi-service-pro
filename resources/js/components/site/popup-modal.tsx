import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const STORAGE_PREFIX = 'popup-seen-';

/**
 * Scheduled promo modal (M19). The server decided *whether* this visitor is in
 * the audience; the browser only remembers *when* they last saw it, so the
 * frequency cap survives a logged-out visit and costs no table.
 */
export function PopupModal() {
    const { site } = usePage<SharedData>().props;
    const popup = site.popup;
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (popup === null) {
            return;
        }

        const key = `${STORAGE_PREFIX}${popup.id}`;
        const seenAt = window.localStorage.getItem(key);

        if (seenAt !== null && popup.frequency_days > 0) {
            const elapsedDays = (Date.now() - Number(seenAt)) / 86_400_000;

            if (elapsedDays < popup.frequency_days) {
                return;
            }
        }

        // A frequency of 0 means "every visit" — nothing is written, so nothing
        // suppresses it next time.
        if (popup.frequency_days > 0) {
            window.localStorage.setItem(key, String(Date.now()));
        }

        setOpen(true);
    }, [popup]);

    if (popup === null) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="overflow-hidden p-0 sm:max-w-md">
                {popup.image_url !== null && <img src={popup.image_url} alt="" className="h-40 w-full object-cover" />}

                <div className="space-y-4 p-6">
                    <DialogHeader>
                        <DialogTitle>{popup.title}</DialogTitle>
                    </DialogHeader>

                    {popup.html !== null && (
                        <div
                            className="prose prose-sm dark:prose-invert max-w-none"
                            // Server-rendered from markdown with raw HTML stripped (D20).
                            dangerouslySetInnerHTML={{ __html: popup.html }}
                        />
                    )}

                    {popup.link_url !== null && popup.link_label !== null && (
                        <Button asChild className="w-full">
                            <a href={popup.link_url}>{popup.link_label}</a>
                        </Button>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
