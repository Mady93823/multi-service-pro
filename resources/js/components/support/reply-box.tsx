import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/lib/i18n';
import { type CannedResponse } from '@/types';
import { useForm } from '@inertiajs/react';
import { Paperclip, Send } from 'lucide-react';
import { useRef } from 'react';

interface ReplyBoxProps {
    /** Fully-resolved POST url for the reply endpoint. */
    action: string;
    /** Admin-only: canned responses that pre-fill the textarea. */
    cannedResponses?: CannedResponse[];
}

export function ReplyBox({ action, cannedResponses }: ReplyBoxProps) {
    const t = useTrans();
    const fileInput = useRef<HTMLInputElement>(null);

    const form = useForm<{ body: string; attachments: File[] }>({ body: '', attachments: [] });

    // Per-file failures come back keyed `attachments.0` — surface whichever hit.
    const attachmentError = form.errors.attachments ?? Object.entries(form.errors).find(([key]) => key.startsWith('attachments.'))?.[1];

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(action, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                if (fileInput.current) {
                    fileInput.current.value = '';
                }
            },
        });
    };

    return (
        <form onSubmit={submit} className="grid gap-3">
            {cannedResponses !== undefined && cannedResponses.length > 0 && (
                <Select value="" onValueChange={(value) => form.setData('body', cannedResponses[Number(value)]?.body ?? form.data.body)}>
                    <SelectTrigger className="w-full sm:w-72">
                        <SelectValue placeholder={t('Insert a canned response…')} />
                    </SelectTrigger>
                    <SelectContent>
                        {cannedResponses.map((response, index) => (
                            <SelectItem key={index} value={String(index)}>
                                {response.title}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )}
            <div className="grid gap-2">
                <Label htmlFor="reply-body" className="sr-only">
                    {t('Your reply')}
                </Label>
                <Textarea
                    id="reply-body"
                    rows={4}
                    placeholder={t('Write a reply…')}
                    value={form.data.body}
                    onChange={(event) => form.setData('body', event.target.value)}
                />
                <InputError message={form.errors.body} />
            </div>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="grid gap-1">
                    <label className="text-muted-foreground flex cursor-pointer items-center gap-1 text-sm">
                        <Paperclip className="h-4 w-4" />
                        <input
                            ref={fileInput}
                            type="file"
                            multiple
                            accept=".jpg,.jpeg,.png,.webp,.pdf"
                            className="text-sm"
                            onChange={(event) => form.setData('attachments', Array.from(event.target.files ?? []))}
                        />
                    </label>
                    <InputError message={attachmentError} />
                </div>
                <Button type="submit" disabled={form.processing}>
                    <Send className="mr-1 h-4 w-4" />
                    {t('Send reply')}
                </Button>
            </div>
        </form>
    );
}
