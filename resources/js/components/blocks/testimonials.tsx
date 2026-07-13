import { Testimonials } from '@/components/marketing/social-proof';
import { type Testimonial } from '@/types';

export interface TestimonialsProps {
    heading: string | null;
    testimonials: Testimonial[];
}

export function TestimonialsBlock({ heading, testimonials }: TestimonialsProps) {
    return <Testimonials testimonials={testimonials} heading={heading} />;
}
