import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { getJson, postForm } from '@/lib/http';
import { useTrans } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { type MediaAsset } from '@/types';
import { ImagePlus, LoaderCircle, Search, Upload } from 'lucide-react';
import { FormEventHandler, useCallback, useEffect, useRef, useState } from 'react';

interface MediaPickerProps {
    value: MediaAsset | null;
    onChange: (asset: MediaAsset | null) => void;
    /** The picture already attached to the record, shown until a new one is picked. */
    currentUrl?: string | null;
    /** Rendered under the preview — e.g. a validation error from the parent form. */
    error?: string;
}

/**
 * The one upload/choose surface (M18). Every module that needs a picture uses
 * this: pick from the library, or upload — an upload lands in the library too,
 * so nothing an admin uploads is invisible in the manager.
 */
export function MediaPicker({ value, onChange, currentUrl = null, error }: MediaPickerProps) {
    const t = useTrans();
    const preview = value?.thumb_url ?? currentUrl;
    const [open, setOpen] = useState(false);
    const [assets, setAssets] = useState<MediaAsset[]>([]);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [failed, setFailed] = useState(false);
    const fileInput = useRef<HTMLInputElement>(null);

    const load = useCallback(
        async (term: string) => {
            setLoading(true);
            setFailed(false);

            try {
                const response = await getJson<{ data: MediaAsset[] }>(`${route('admin.media.picker')}?search=${encodeURIComponent(term)}`);
                setAssets(response.data);
            } catch {
                setFailed(true);
            } finally {
                setLoading(false);
            }
        },
        [], // route() and the setters are stable
    );

    useEffect(() => {
        if (open) {
            void load(search);
        }
        // Re-running on `search` would fire a request per keystroke; the search
        // form submits explicitly instead.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, load]);

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        void load(search);
    };

    const upload = async (files: FileList | null) => {
        if (files === null || files.length === 0) {
            return;
        }

        const form = new FormData();
        Array.from(files).forEach((file) => form.append('files[]', file));

        setUploading(true);
        setFailed(false);

        try {
            const response = await postForm<{ data: MediaAsset[] }>(route('admin.media.picker.store'), form);
            setAssets((current) => [...response.data, ...current]);

            if (response.data.length > 0) {
                onChange(response.data[0]);
                setOpen(false);
            }
        } catch {
            setFailed(true);
        } finally {
            setUploading(false);

            if (fileInput.current !== null) {
                fileInput.current.value = '';
            }
        }
    };

    return (
        <div className="grid gap-2">
            <div className="flex items-start gap-3">
                {preview !== null ? (
                    <img src={preview} alt="" className="h-20 w-32 rounded border object-cover" />
                ) : (
                    <div className="text-muted-foreground flex h-20 w-32 items-center justify-center rounded border border-dashed text-xs">
                        {t('No image')}
                    </div>
                )}

                <div className="flex flex-col gap-2">
                    <Dialog open={open} onOpenChange={setOpen}>
                        <DialogTrigger asChild>
                            <Button type="button" variant="outline" size="sm">
                                <ImagePlus className="h-4 w-4" />
                                {value === null ? t('Choose image') : t('Change image')}
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="max-w-3xl">
                            <DialogHeader>
                                <DialogTitle>{t('Media library')}</DialogTitle>
                                <DialogDescription>{t('Pick a file, or upload a new one — it is added to the library.')}</DialogDescription>
                            </DialogHeader>

                            <div className="flex flex-wrap items-center gap-2">
                                <form onSubmit={submitSearch} className="flex items-center gap-2">
                                    <Input
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        placeholder={t('Search files...')}
                                        className="w-56"
                                    />
                                    <Button type="submit" variant="outline" size="icon" aria-label={t('Search')}>
                                        <Search className="h-4 w-4" />
                                    </Button>
                                </form>

                                <Button type="button" variant="secondary" size="sm" disabled={uploading} onClick={() => fileInput.current?.click()}>
                                    {uploading ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />}
                                    {t('Upload')}
                                </Button>
                                <input
                                    ref={fileInput}
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    multiple
                                    className="hidden"
                                    onChange={(e) => void upload(e.target.files)}
                                />
                            </div>

                            {failed && <p className="text-destructive text-sm">{t('Something went wrong. Please try again.')}</p>}

                            <div className="grid max-h-96 grid-cols-2 gap-3 overflow-y-auto sm:grid-cols-4">
                                {loading && <p className="text-muted-foreground col-span-full py-6 text-center text-sm">{t('Loading...')}</p>}

                                {!loading && assets.length === 0 && (
                                    <p className="text-muted-foreground col-span-full py-6 text-center text-sm">
                                        {t('The library is empty. Upload your first file.')}
                                    </p>
                                )}

                                {!loading &&
                                    assets.map((asset) => (
                                        <button
                                            key={asset.id}
                                            type="button"
                                            onClick={() => {
                                                onChange(asset);
                                                setOpen(false);
                                            }}
                                            className={cn(
                                                'group overflow-hidden rounded-lg border text-left transition-colors',
                                                value?.id === asset.id ? 'border-primary ring-primary/40 ring-2' : 'hover:border-primary/60',
                                            )}
                                        >
                                            {asset.thumb_url !== null ? (
                                                <img src={asset.thumb_url} alt="" className="h-24 w-full object-cover" />
                                            ) : (
                                                <div className="bg-muted h-24 w-full" />
                                            )}
                                            <span className="block truncate px-2 py-1 text-xs">{asset.name}</span>
                                        </button>
                                    ))}
                            </div>
                        </DialogContent>
                    </Dialog>

                    {value !== null && (
                        <Button type="button" variant="ghost" size="sm" className="w-fit" onClick={() => onChange(null)}>
                            {t('Remove')}
                        </Button>
                    )}
                </div>
            </div>

            {error !== undefined && <p className="text-destructive text-sm">{error}</p>}
        </div>
    );
}
