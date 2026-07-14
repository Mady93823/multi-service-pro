import { NewsletterForm } from '@/components/site/newsletter-form';
import { useTrans } from '@/lib/i18n';
import { type SharedData, type SiteMenuLink } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Mail, MapPin, Phone } from 'lucide-react';

const SOCIAL_LABELS: Record<string, string> = {
    facebook: 'Facebook',
    instagram: 'Instagram',
    x: 'X',
    youtube: 'YouTube',
    linkedin: 'LinkedIn',
    whatsapp: 'WhatsApp',
};

/**
 * Storefront footer (M19): columns come from the footer menus, the about blurb
 * and contact block from the Appearance settings, the icons from Social links.
 * Nothing here is hardcoded copy — the white-label rule reaches the footer too.
 */
export default function SiteFooter() {
    const { name, site, footer_pages } = usePage<SharedData>().props;
    const t = useTrans();

    const { appearance, social } = site;
    const columns = [site.menus.footer_1 ?? [], site.menus.footer_2 ?? [], site.menus.footer_3 ?? []];
    const hasColumns = columns.some((column) => column.length > 0);
    const socials = Object.entries(social);

    const copyright = appearance.copyright ?? `© ${new Date().getFullYear()} ${name}`;

    // The simple variant (and an install whose footer menus are still empty)
    // falls back to the M14 footer-page links, so the legal links never vanish.
    if (appearance.footer_variant === 'simple' || !hasColumns) {
        return (
            <footer className="border-sidebar-border/80 text-muted-foreground border-t py-6 text-center text-sm">
                {footer_pages.length > 0 && (
                    <nav className="mb-3 flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
                        {footer_pages.map((page) => (
                            <Link key={page.slug} href={`/p/${page.slug}`} className="hover:text-foreground underline-offset-2 hover:underline">
                                {page.title}
                            </Link>
                        ))}
                    </nav>
                )}
                {socials.length > 0 && <SocialRow socials={socials} className="mb-3 justify-center" />}
                {site.newsletter && (
                    <div className="mx-auto mb-4 max-w-sm text-left">
                        <NewsletterForm />
                    </div>
                )}
                {copyright}
            </footer>
        );
    }

    return (
        <footer className="bg-surface border-t">
            <div className="mx-auto grid w-full max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-4 lg:px-8">
                <div className="space-y-4">
                    <p className="text-lg font-semibold tracking-tight">{name}</p>
                    {appearance.footer_about !== null && <p className="text-muted-foreground text-sm leading-relaxed">{appearance.footer_about}</p>}
                    {socials.length > 0 && <SocialRow socials={socials} />}
                    {site.newsletter && <NewsletterForm />}
                </div>

                {columns.map((column, index) =>
                    column.length === 0 ? null : (
                        <div key={index}>
                            <FooterColumn links={column} />
                        </div>
                    ),
                )}

                {(appearance.contact_email !== null || appearance.contact_phone !== null || appearance.contact_address !== null) && (
                    <div className="space-y-3">
                        <p className="text-xs font-semibold tracking-[0.14em] uppercase">{t('Contact')}</p>
                        <ul className="text-muted-foreground space-y-2.5 text-sm">
                            {appearance.contact_email !== null && (
                                <li className="flex items-start gap-2">
                                    <Mail className="mt-0.5 h-4 w-4 shrink-0" />
                                    <a href={`mailto:${appearance.contact_email}`} className="hover:text-foreground">
                                        {appearance.contact_email}
                                    </a>
                                </li>
                            )}
                            {appearance.contact_phone !== null && (
                                <li className="flex items-start gap-2">
                                    <Phone className="mt-0.5 h-4 w-4 shrink-0" />
                                    <a href={`tel:${appearance.contact_phone}`} className="hover:text-foreground">
                                        {appearance.contact_phone}
                                    </a>
                                </li>
                            )}
                            {appearance.contact_address !== null && (
                                <li className="flex items-start gap-2">
                                    <MapPin className="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>{appearance.contact_address}</span>
                                </li>
                            )}
                        </ul>
                    </div>
                )}
            </div>

            <div className="text-muted-foreground border-t py-6 text-center text-sm">{copyright}</div>
        </footer>
    );
}

/**
 * The first link is the column's own heading when the admin nested the rest
 * under it — which is how a menu builder expresses "Company: About, Careers".
 */
function FooterColumn({ links }: { links: SiteMenuLink[] }) {
    return (
        <ul className="space-y-3 text-sm">
            {links.map((link) => (
                <li key={`${link.label}-${link.url}`}>
                    <Link
                        href={link.url}
                        className={
                            link.children.length > 0
                                ? 'hover:text-primary text-xs font-semibold tracking-[0.14em] uppercase transition-colors'
                                : 'text-muted-foreground hover:text-foreground transition-colors'
                        }
                    >
                        {link.label}
                    </Link>

                    {link.children.length > 0 && (
                        <ul className="mt-3 space-y-2.5">
                            {link.children.map((child) => (
                                <li key={`${child.label}-${child.url}`}>
                                    <Link href={child.url} className="text-muted-foreground hover:text-foreground transition-colors">
                                        {child.label}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </li>
            ))}
        </ul>
    );
}

/**
 * Names, not brand glyphs: lucide dropped its brand icons, and shipping other
 * companies' marks in a white-label product is a licensing problem the buyer
 * inherits.
 */
function SocialRow({ socials, className }: { socials: [string, string][]; className?: string }) {
    return (
        <div className={`flex flex-wrap items-center gap-2 ${className ?? ''}`}>
            {socials.map(([network, url]) => (
                <a
                    key={network}
                    href={url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-muted-foreground hover:text-foreground hover:border-foreground/40 rounded-full border px-3 py-1 text-xs"
                >
                    {SOCIAL_LABELS[network] ?? network}
                </a>
            ))}
        </div>
    );
}
