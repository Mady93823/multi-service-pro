import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    roles: string[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface Flash {
    success?: string | null;
    error?: string | null;
}

export interface Branding {
    logo_url: string | null;
    primary_color: string | null;
}

export interface Localization {
    currency: string;
    locale: string;
    timezone: string;
}

export interface SharedData {
    name: string;
    translations: Record<string, string>;
    branding: Branding;
    localization: Localization;
    auth: Auth;
    flash: Flash;
    [key: string]: unknown;
}

export interface Category {
    id: number;
    parent_id: number | null;
    name: string;
    slug: string;
    icon_url: string | null;
    image_url: string | null;
    sort_order: number;
    is_active: boolean;
    children?: Category[];
    services?: Service[];
    services_count?: number;
}

export type PricingType = 'fixed' | 'hourly' | 'inspection';

export interface ServiceAddon {
    id: number;
    name: string;
    price: string;
    is_active: boolean;
}

export interface Service {
    id: number;
    category_id: number;
    name: string;
    slug: string;
    short_description: string | null;
    description: string | null;
    pricing_type: PricingType;
    price: string;
    duration_minutes: number | null;
    is_featured: boolean;
    is_active: boolean;
    sort_order: number;
    image_thumb_url: string | null;
    image_card_url: string | null;
    image_hero_url: string | null;
    category?: Category;
    addons?: ServiceAddon[];
    related?: Service[];
    addons_count?: number;
}

export interface PaginationLinkItem {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
        links: PaginationLinkItem[];
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
