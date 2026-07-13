import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface BlogValues {
    blog_enabled: boolean;
    blog_posts_per_page: number;
    blog_show_author: boolean;
    blog_related_count: number;
}

export default function BlogForm({ values }: { values: BlogValues }) {
    const t = useTrans();

    const { data, setData, put, processing, errors } = useForm({ ...values });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'blog'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <label className="flex items-center justify-between gap-4 text-sm">
                <span>
                    <span className="font-medium">{t('Enable the blog')}</span>
                    <span className="text-muted-foreground block">{t('Off removes /blog and every post from the site entirely.')}</span>
                </span>
                <Switch checked={data.blog_enabled} onCheckedChange={(checked) => setData('blog_enabled', checked)} />
            </label>

            <label className="flex items-center justify-between gap-4 text-sm">
                <span className="font-medium">{t('Show the author')}</span>
                <Switch checked={data.blog_show_author} onCheckedChange={(checked) => setData('blog_show_author', checked)} />
            </label>

            <div className="grid gap-2">
                <Label htmlFor="blog_posts_per_page">{t('Posts per page')}</Label>
                <Input
                    id="blog_posts_per_page"
                    type="number"
                    min={1}
                    max={50}
                    value={data.blog_posts_per_page}
                    onChange={(e) => setData('blog_posts_per_page', Number(e.target.value))}
                    className="w-40"
                    required
                />
                <InputError message={errors.blog_posts_per_page} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="blog_related_count">{t('Related posts to show')}</Label>
                <Input
                    id="blog_related_count"
                    type="number"
                    min={0}
                    max={12}
                    value={data.blog_related_count}
                    onChange={(e) => setData('blog_related_count', Number(e.target.value))}
                    className="w-40"
                    required
                />
                <p className="text-muted-foreground text-xs">{t('Set to 0 to hide the “Keep reading” section.')}</p>
                <InputError message={errors.blog_related_count} />
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}
