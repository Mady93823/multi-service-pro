import { HeroBanners, StripBanners } from '@/components/marketing/banners';
import { type Banner } from '@/types';

export interface BannersProps {
    placement: string;
    banners: Banner[];
}

export function BannersBlock({ placement, banners }: BannersProps) {
    if (banners.length === 0) {
        return null;
    }

    return placement === 'home_hero' ? <HeroBanners banners={banners} /> : <StripBanners banners={banners} />;
}
