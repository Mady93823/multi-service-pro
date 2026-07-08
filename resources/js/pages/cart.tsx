import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import PublicLayout from '@/layouts/public-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type CartLine, type CartSummary, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ImageIcon, Minus, Plus, ShoppingCart, Trash2 } from 'lucide-react';

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

    return (
        <PublicLayout>
            <Head title={t('Your cart')} />

            <h1 className="text-xl font-semibold">{t('Your cart')}</h1>

            {lines.length === 0 ? (
                <div className="flex flex-col items-center gap-4 py-20 text-center">
                    <ShoppingCart className="text-muted-foreground/40 h-12 w-12" />
                    <p className="text-muted-foreground">{t('Your cart is empty.')}</p>
                    <Button asChild>
                        <Link href={route('catalog.index')}>{t('Browse services')}</Link>
                    </Button>
                </div>
            ) : (
                <div className="grid gap-8 py-6 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        {blockedServices.length > 0 && (
                            <p className="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200">
                                {t('Not available at your default address: :services. You can pick a different address at checkout.', {
                                    services: blockedServices.join(', '),
                                })}
                            </p>
                        )}

                        {lines.map((line) => (
                            <Card key={line.key}>
                                <CardContent className="flex items-start gap-4 p-4">
                                    {line.service.image_thumb_url ? (
                                        <img src={line.service.image_thumb_url} alt="" className="h-16 w-16 rounded-lg object-cover" />
                                    ) : (
                                        <div className="bg-muted flex h-16 w-16 items-center justify-center rounded-lg">
                                            <ImageIcon className="text-muted-foreground/50 h-6 w-6" />
                                        </div>
                                    )}
                                    <div className="min-w-0 flex-1">
                                        {line.service.category_slug ? (
                                            <Link
                                                href={route('catalog.show', [line.service.category_slug, line.service.slug])}
                                                className="font-medium hover:underline"
                                            >
                                                {line.service.name}
                                            </Link>
                                        ) : (
                                            <span className="font-medium">{line.service.name}</span>
                                        )}
                                        {line.addons.length > 0 && (
                                            <p className="text-muted-foreground mt-0.5 text-sm">
                                                {t('Add-ons: :names', { names: line.addons.map((addon) => addon.name).join(', ') })}
                                            </p>
                                        )}
                                        <div className="mt-2 flex items-center gap-2">
                                            <Button
                                                variant="outline"
                                                size="icon"
                                                className="h-7 w-7"
                                                aria-label={t('Decrease quantity')}
                                                onClick={() => setQty(line.key, line.qty - 1)}
                                            >
                                                <Minus className="h-3 w-3" />
                                            </Button>
                                            <span className="w-6 text-center text-sm font-medium">{line.qty}</span>
                                            <Button
                                                variant="outline"
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
                                        <span className="font-semibold">{money(line.line_total)}</span>
                                        <Button variant="ghost" size="icon" aria-label={t('Remove from cart')} onClick={() => remove(line.key)}>
                                            <Trash2 className="text-muted-foreground h-4 w-4" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>

                    <div>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">{t('Order summary')}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">{t('Items')}</span>
                                    <span>{money(summary.subtotal)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        {summary.tax_label} ({summary.tax_percent}%)
                                    </span>
                                    <span>{money(summary.tax)}</span>
                                </div>
                                <Separator />
                                <div className="flex justify-between text-base font-semibold">
                                    <span>{t('Total')}</span>
                                    <span>{money(summary.total)}</span>
                                </div>
                                {auth.user ? (
                                    <Button asChild className="mt-4 w-full" size="lg">
                                        <Link href={route('checkout.show')}>{t('Proceed to checkout')}</Link>
                                    </Button>
                                ) : (
                                    <>
                                        <Button asChild className="mt-4 w-full" size="lg">
                                            <Link href={route('login')}>{t('Sign in to continue')}</Link>
                                        </Button>
                                        <p className="text-muted-foreground text-center text-xs">{t('Your cart is saved while you sign in.')}</p>
                                    </>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            )}
        </PublicLayout>
    );
}
