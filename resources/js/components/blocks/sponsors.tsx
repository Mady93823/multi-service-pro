import { Sponsors } from '@/components/marketing/social-proof';
import { type Sponsor } from '@/types';

export interface SponsorsProps {
    heading: string | null;
    sponsors: Sponsor[];
}

export function SponsorsBlock({ heading, sponsors }: SponsorsProps) {
    return <Sponsors sponsors={sponsors} heading={heading} />;
}
