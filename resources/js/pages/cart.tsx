import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import PublicLayout from '@/layouts/public-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type CartLine, type CartSummary, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, ImageIcon, Minus, Plus, ShieldCheck, ShoppingCart, Trash2, TriangleAlert } from 'lucide-react';

interface CartPageProps {
    lines: CartLine[];
    summary: CartSummary;
    blocked_services: string[];
    has_default_address: boolean;
}

export default function CartPage({ lines, summary, blocked_services: blockedServices }: CartPageProps) {
    const t = useTrans();
    const money = useMoney();
    const { auth } = usePage<SharedData>().props;

    const setQty = (key: string, qty: number) => {
        router.patch(route('cart.update', key), { qty }, { preserveScroll: true });
    };

    const remove = (key: string) => {
        router.delete(route('cart.remove', key), { preserveScroll: true });
    };

    if (lines.length === 0) {
        return (
            <PublicLayout>
                <Head title={t('Your cart')} />

                <div className="flex flex-col items-center gap-5 py-24 text-center">
                    <span className="bg-muted text-muted-foreground flex h-16 w-16 items-center justify-center rounded-2xl">
                        <ShoppingCart className="h-7 w-7" />
                    </span>
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">{t('Your cart is empty.')}</h1>
                        <p className="text-muted-foreground">{t('Search for a job, or browse the categories below.')}</p>
                    </div>
                    <Button asChild size="lg" className="rounded-xl">
                        <Link href={route('catalog.index')}>{t('Browse services')}</Link>
                    </Button>
                </div>
            </PublicLayout>
        );
    }

    return (
        <PublicLayout>
            <Head title={t('Your cart')} />

            <h1 className="text-3xl font-bold tracking-tight">{t('Your cart')}</h1>

            <div className="grid gap-8 py-8 lg:grid-cols-3">
                <div className="space-y-4 lg:col-span-2">
                    {blockedServices.length > 0 && (
                        <p className="border-highlight/40 bg-highlight/10 flex items-start gap-2.5 rounded-xl border px-4 py-3 text-sm">
                            <TriangleAlert className="text-highlight mt-0.5 h-4 w-4 shrink-0" aria-hidden />
                            {t('Not available at your default address: :services. You can pick a different address at checkout.', {
                                services: blockedServices.join(', '),
                            })}
                        </p>
                    )}

                    {lines.map((line) => (
                        <Card key={line.key} className="flex-row items-start gap-4 p-4">
                            {line.service.image_thumb_url ? (
                                <img src={line.service.image_thumb_url} alt="" className="h-20 w-20 shrink-0 rounded-xl object-cover" />
                            ) : (
                                <div className="bg-muted flex h-20 w-20 shrink-0 items-center justify-center rounded-xl">
                                    <ImageIcon className="text-muted-foreground/40 h-6 w-6" />
                                </div>
                            )}

                            <div className="min-w-0 flex-1">
                                {line.service.category_slug ? (
                                    <Link
                                        href={route('catalog.show', [line.service.category_slug, line.service.slug])}
                                        className="hover:text-primary font-semibold transition-colors"
                                    >
                                        {line.service.name}
                                    </Link>
                                ) : (
                                    <span className="font-semibold">{line.service.name}</span>
                                )}

                                {line.addons.length > 0 && (
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        {t('Add-ons: :names', { names: line.addons.map((addon) => addon.name).join(', ') })}
                                    </p>
                                )}

                                <div className="mt-3 flex w-fit items-center gap-1 rounded-lg border p-0.5">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="h-7 w-7"
                                        aria-label={t('Decrease quantity')}
                                        onClick={() => setQty(line.key, line.qty - 1)}
                                    >
                                        <Minus className="h-3 w-3" />
                                    </Button>
                                    <span className="w-7 text-center text-sm font-semibold">{line.qty}</span>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="h-7 w-7"
                                        aria-label={t('Increase quantity')}
                                        disabled={line.qty >= 10}
                                        onClick={() => setQty(line.key, line.qty + 1)}
                                    >
                                        <Plus className="h-3 w-3" />
                                    </Button>
                                </div>
                            </div>

                            <div className="flex flex-col items-end gap-2">
                                <span className="text-lg font-bold">{money(line.line_total)}</span>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="hover:text-destructive h-8 w-8"
                                    aria-label={t('Remove from cart')}
                                    onClick={() => remove(line.key)}
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        </Card>
                    ))}
                </div>

                <div>
                    <Card className="gap-0 p-6 shadow-lg lg:sticky lg:top-24">
                        <h2 className="text-lg font-semibold">{t('Order summary')}</h2>

                        <div className="mt-5 space-y-3 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">{t('Items')}</span>
                                <span className="font-medium">{money(summary.subtotal)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    {summary.tax_label} ({summary.tax_percent}%)
                                </span>
                                <span className="font-medium">{money(summary.tax)}</span>
                            </div>

                            <Separator />

                            <div className="flex items-center justify-between">
                                <span className="font-semibold">{t('Total')}</span>
                                <span className="text-2xl font-bold">{money(summary.total)}</span>
                            </div>
                        </div>

                        {auth.user ? (
                            <Button asChild className="mt-6 h-12 w-full rounded-xl text-base" size="lg">
                                <Link href={route('checkout.show')}>
                                    {t('Proceed to checkout')}
                                    <ArrowRight className="h-4 w-4" />
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild className="mt-6 h-12 w-full rounded-xl text-base" size="lg">
                                    <Link href={route('login')}>{t('Sign in to continue')}</Link>
                                </Button>
                                <p className="text-muted-foreground mt-3 text-center text-xs">{t('Your cart is saved while you sign in.')}</p>
                            </>
                        )}

                        {/* Adding a service lands here, so the way back out has to be on this card — not the browser's Back button. */}
                        <Button asChild variant="ghost" className="mt-2 h-11 w-full rounded-xl" size="lg">
                            <Link href={route('catalog.index')}>
                                <ArrowLeft className="h-4 w-4" />
                                {t('Continue shopping')}
                            </Link>
                        </Button>

                        <p className="text-muted-foreground mt-4 flex items-center justify-center gap-1.5 text-xs">
                            <ShieldCheck className="h-3.5 w-3.5" aria-hidden />
                            {t('No payment now. You choose how to pay at checkout.')}
                        </p>
                    </Card>
                </div>
            </div>
        </PublicLayout>
    );
}
