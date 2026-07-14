import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useLayoutEffect } from 'react';

/**
 * Makes `branding.primary_color` mean something.
 *
 * The setting has existed since M14 and was **applied to nothing**: an admin
 * could pick a colour, save it, and the site would not change by one pixel,
 * because the theme's `--primary` was a hard-coded near-black in `app.css`. A
 * white-label product whose one branding control is decorative is not a
 * white-label product.
 *
 * The shipped default (indigo) lives in `app.css`, which is what a fresh install
 * and a print stylesheet get. This only *overrides* it, and only when the admin
 * has actually chosen a colour — so removing the setting reverts to the
 * designed default rather than to nothing.
 */
export function BrandTheme() {
    const { branding } = usePage<SharedData>().props;
    const color = branding.primary_color;

    // Layout effect, not effect: this must land before the browser paints, or a
    // brand-coloured site flashes indigo on every full page load.
    useLayoutEffect(() => {
        const root = document.documentElement;

        const properties = ['--primary', '--primary-foreground', '--ring', '--sidebar-primary', '--sidebar-primary-foreground', '--sidebar-ring'];

        if (color === null || !/^#[0-9a-fA-F]{6}$/.test(color)) {
            properties.forEach((property) => root.style.removeProperty(property));

            return;
        }

        const foreground = readableOn(color);

        root.style.setProperty('--primary', color);
        root.style.setProperty('--primary-foreground', foreground);
        root.style.setProperty('--ring', color);
        root.style.setProperty('--sidebar-primary', color);
        root.style.setProperty('--sidebar-primary-foreground', foreground);
        root.style.setProperty('--sidebar-ring', color);
    }, [color]);

    return null;
}

/**
 * Black or white, whichever the eye can actually read on this colour.
 *
 * A buyer who picks a pale yellow brand colour must not end up with white text
 * on it — that is an unreadable button, shipped by us, on their site. The
 * threshold is the WCAG relative-luminance one, not a guess at "is it light".
 */
function readableOn(hex: string): string {
    const channel = (value: number): number => {
        const c = value / 255;

        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    };

    const r = channel(parseInt(hex.slice(1, 3), 16));
    const g = channel(parseInt(hex.slice(3, 5), 16));
    const b = channel(parseInt(hex.slice(5, 7), 16));

    const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b;

    return luminance > 0.45 ? 'hsl(240, 10%, 8%)' : 'hsl(0, 0%, 100%)';
}
