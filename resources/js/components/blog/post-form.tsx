import InputError from '@/components/input-error';
import { MediaPicker } from '@/components/media/media-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/lib/i18n';
import { type BlogCategory, type BlogPost, type MediaAsset } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

/** Radix `SelectItem` cannot hold an empty string, so "no category" rides a sentinel. */
const NONE = '__none__';

interface PostFormProps {
    post?: BlogPost;
    categories: BlogCategory[];
}

export function PostForm({ post, categories }: PostFormProps) {
    const t = useTrans();
    const [asset, setAsset] = useState<MediaAsset | null>(null);

    const {
        data,
        setData,
        transform,
        post: submitPost,
        put,
        processing,
        errors,
    } = useForm({
        title: post?.title ?? '',
        slug: post?.slug ?? '',
        blog_category_id: post?.category?.id ?? null,
        excerpt: post?.excerpt ?? '',
        body: post?.body ?? '',
        tags: (post?.tags ?? []).join(', '),
        is_featured: post?.is_featured ?? false,
        is_published: post?.is_published ?? false,
        published_at: post?.published_at?.slice(0, 10) ?? '',
        meta_title: post?.meta_title ?? '',
        meta_description: post?.meta_description ?? '',
        media_asset_id: null as number | null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // Tags are typed as one comma-separated line and posted as a list. The
        // split happens in `transform`, which runs at submit time — `setData`
        // is React state and would still be one render behind (M18's landmine).
        transform((payload) => ({
            ...payload,
            tags: payload.tags
                .split(',')
                .map((tag) => tag.trim())
                .filter((tag) => tag !== ''),
        }));

        if (post) {
            put(route('admin.blog.update', post.id));
        } else {
            submitPost(route('admin.blog.store'));
        }
    };

    return (
        <form onSubmit={submit} className="max-w-3xl space-y-6">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="title">{t('Title')}</Label>
                    <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} required maxLength={150} />
                    <InputError message={errors.title} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="slug">{t('Slug')}</Label>
                    <Input id="slug" value={data.slug} onChange={(e) => setData('slug', e.target.value)} placeholder={t('Auto from title')} />
                    <p className="text-muted-foreground text-xs">{t('Public URL: /blog/:slug', { slug: data.slug || '…' })}</p>
                    <InputError message={errors.slug} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="blog_category_id">{t('Category')}</Label>
                    <Select
                        value={data.blog_category_id === null ? NONE : String(data.blog_category_id)}
                        onValueChange={(value) => setData('blog_category_id', value === NONE ? null : Number(value))}
                    >
                        <SelectTrigger id="blog_category_id">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NONE}>{t('Uncategorised')}</SelectItem>
                            {categories.map((category) => (
                                <SelectItem key={category.id} value={String(category.id)}>
                                    {category.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.blog_category_id} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="tags">{t('Tags')}</Label>
                    <Input id="tags" value={data.tags} onChange={(e) => setData('tags', e.target.value)} placeholder={t('cleaning, tips')} />
                    <p className="text-muted-foreground text-xs">{t('Comma-separated.')}</p>
                    <InputError message={errors.tags} />
                </div>
            </div>

            <div className="space-y-2">
                <Label>{t('Cover image')}</Label>
                <MediaPicker
                    value={asset}
                    onChange={(picked) => {
                        setAsset(picked);
                        setData('media_asset_id', picked?.id ?? null);
                    }}
                    currentUrl={post?.cover_url ?? null}
                    error={errors.media_asset_id}
                />
            </div>

            <div className="space-y-2">
                <Label htmlFor="excerpt">{t('Excerpt')}</Label>
                <Textarea id="excerpt" value={data.excerpt} onChange={(e) => setData('excerpt', e.target.value)} rows={2} maxLength={500} />
                <p className="text-muted-foreground text-xs">{t('Shown on the blog index and in the RSS feed.')}</p>
                <InputError message={errors.excerpt} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="body">{t('Body (Markdown)')}</Label>
                <Textarea
                    id="body"
                    value={data.body}
                    onChange={(e) => setData('body', e.target.value)}
                    rows={18}
                    required
                    className="font-mono text-sm"
                />
                <p className="text-muted-foreground text-xs">{t('Headings, lists and links are supported. Raw HTML is stripped on display.')}</p>
                <InputError message={errors.body} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="meta_title">{t('SEO title')}</Label>
                    <Input id="meta_title" value={data.meta_title} onChange={(e) => setData('meta_title', e.target.value)} maxLength={150} />
                    <p className="text-muted-foreground text-xs">{t('Blank uses the post title.')}</p>
                    <InputError message={errors.meta_title} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="meta_description">{t('SEO description')}</Label>
                    <Input
                        id="meta_description"
                        value={data.meta_description}
                        onChange={(e) => setData('meta_description', e.target.value)}
                        maxLength={300}
                    />
                    <p className="text-muted-foreground text-xs">{t('Blank uses the excerpt.')}</p>
                    <InputError message={errors.meta_description} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="flex items-center gap-3">
                    <Switch id="is_published" checked={data.is_published} onCheckedChange={(v) => setData('is_published', v)} />
                    <Label htmlFor="is_published">{t('Published')}</Label>
                </div>
                <div className="flex items-center gap-3">
                    <Switch id="is_featured" checked={data.is_featured} onCheckedChange={(v) => setData('is_featured', v)} />
                    <Label htmlFor="is_featured">{t('Featured')}</Label>
                </div>
                <div className="space-y-2">
                    <Label htmlFor="published_at">{t('Publish on')}</Label>
                    <Input id="published_at" type="date" value={data.published_at} onChange={(e) => setData('published_at', e.target.value)} />
                    <p className="text-muted-foreground text-xs">{t('A future date schedules the post; it stays hidden until then.')}</p>
                    <InputError message={errors.published_at} />
                </div>
            </div>

            <div className="flex gap-2">
                <Button type="submit" disabled={processing}>
                    {post ? t('Save changes') : t('Create post')}
                </Button>
                <Button asChild variant="outline" type="button">
                    <Link href={route('admin.blog.index')}>{t('Cancel')}</Link>
                </Button>
            </div>
        </form>
    );
}
