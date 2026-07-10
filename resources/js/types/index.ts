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

export interface AppNotification {
    id: string;
    title: string;
    body: string;
    url: string | null;
    read_at: string | null;
    created_at: string | null;
}

export interface NotificationFeed {
    unread_count: number;
    recent: AppNotification[];
}

export interface SharedData {
    name: string;
    translations: Record<string, string>;
    branding: Branding;
    localization: Localization;
    auth: Auth;
    flash: Flash;
    cart: { count: number };
    notifications: NotificationFeed;
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
    /** Null inherits the parent category, then the platform rate (M09). */
    commission_percent: string | null;
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
    zone_ids?: number[];
}

// Type alias (not interface) so it satisfies Inertia's FormDataConvertible
// index-signature check when used inside useForm payloads.
export type GeoJsonPolygon = {
    type: 'Polygon';
    coordinates: number[][][];
};

export interface Zone {
    id: number;
    name: string;
    city: string;
    geojson: GeoJsonPolygon;
    is_active: boolean;
    services_count?: number;
    addresses_count?: number;
}

export type AddressLabel = 'home' | 'work' | 'other';

export interface Address {
    id: number;
    label: AddressLabel;
    line1: string;
    line2: string | null;
    city: string;
    postal_code: string;
    lat: number;
    lng: number;
    is_default: boolean;
    zone?: Zone | null;
}

export type BookingStatus =
    | 'pending_payment'
    | 'placed'
    | 'searching'
    | 'assigned'
    | 'accepted'
    | 'en_route'
    | 'arrived'
    | 'in_progress'
    | 'completed'
    | 'cancelled_customer'
    | 'cancelled_provider'
    | 'cancelled_admin'
    | 'expired'
    | 'failed_payment';

export interface AddressSnapshot {
    label: AddressLabel;
    line1: string;
    line2: string | null;
    city: string;
    postal_code: string;
    lat: number;
    lng: number;
}

export interface BookingItemAddon {
    id: number;
    name: string;
    price: string;
}

export interface BookingItem {
    id: number;
    service_id: number;
    name: string;
    price: string;
    qty: number;
    addons: BookingItemAddon[];
    line_total: string;
}

export interface BookingHistoryEntry {
    id: number;
    from_status: BookingStatus | null;
    to_status: BookingStatus;
    actor_type: 'customer' | 'provider' | 'admin' | 'system';
    note: string | null;
    created_at: string;
    created_label: string;
}

export interface TaxBreakup {
    label: string;
    percent: number;
    cgst: number;
    sgst: number;
    igst: number;
}

export interface Booking {
    id: number;
    code: string;
    status: BookingStatus;
    scheduled_at: string;
    scheduled_label: string;
    slot_label: string;
    address: AddressSnapshot;
    zone?: string | null;
    customer?: { id: number; name: string } | null;
    provider?: { id: number; name: string } | null;
    items?: BookingItem[];
    items_count?: number;
    subtotal: string;
    addon_total: string;
    discount: string;
    tax: string;
    tax_breakup: TaxBreakup | null;
    total: string;
    payment_status: 'unpaid' | 'paid' | 'refunded' | 'partial_refund';
    payment_method: 'cash' | 'gateway' | 'wallet';
    cancellation_fee: string | null;
    notes: string | null;
    cancel_reason: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
    created_at: string | null;
    job_otp_code?: string;
    history?: BookingHistoryEntry[];
    photo_urls?: string[];
}

export interface Review {
    id: number;
    rating: number;
    comment: string | null;
    is_hidden: boolean;
    hidden_reason: string | null;
    created_at: string | null;
    customer_name?: string;
    provider_name?: string;
    booking_code?: string;
    booking_id: number;
    photo_urls?: string[];
}

export interface ReviewSummary {
    average: number;
    count: number;
    /** Star value (5..1) to how many visible reviews gave it. */
    distribution: Record<number, number>;
}

export interface SlotOption {
    value: string;
    label: string;
}

export interface SlotDay {
    date: string;
    label: string;
    slots: SlotOption[];
}

export interface CartLineService {
    id: number;
    name: string;
    slug: string;
    category_slug: string | null;
    image_thumb_url: string | null;
    price: string;
    duration_minutes: number | null;
}

export interface CartLine {
    key: string;
    qty: number;
    service: CartLineService;
    addons: BookingItemAddon[];
    unit_total: string;
    line_total: string;
}

export interface CartSummary {
    subtotal: string;
    discount?: string;
    tax: string;
    tax_label: string;
    tax_percent: number;
    total: string;
}

export interface PaginationLinkItem {
    url: string | null;
    label: string;
    active: boolean;
}

/** Laravel's raw paginator JSON — what you get when a controller passes a
 *  paginator straight to Inertia instead of wrapping it in a Resource. */
export interface NativePaginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
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

export type ProviderApprovalStatus = 'pending' | 'approved' | 'rejected' | 'suspended';

export type ProviderDocumentType = 'id_proof' | 'address_proof' | 'certificate' | 'photo';

export type ProviderDocumentStatus = 'pending' | 'approved' | 'rejected';

// Type alias (not interface) so it satisfies Inertia's FormDataConvertible.
export type WorkingDay = {
    off: boolean;
    start?: string;
    end?: string;
};

export type WeekDayKey = 'mon' | 'tue' | 'wed' | 'thu' | 'fri' | 'sat' | 'sun';

export type WorkingHours = Record<WeekDayKey, WorkingDay>;

export interface ProviderDocument {
    id: number;
    type: ProviderDocumentType;
    status: ProviderDocumentStatus;
    reject_reason: string | null;
    is_pdf: boolean;
    url: string;
    uploaded_at: string | null;
}

export interface ProviderBlackout {
    id: number;
    starts_on: string;
    ends_on: string;
    starts_label: string;
    ends_label: string;
    reason: string | null;
}

export type OfferStatus = 'offered' | 'accepted' | 'declined' | 'expired';

export type DispatchMode = 'nearest' | 'broadcast' | 'manual';

export interface DispatchOffer {
    id: number;
    status: OfferStatus;
    strategy: DispatchMode;
    round: number;
    distance_km: string | null;
    offered_at: string | null;
    expires_at: string | null;
    provider?: { id: number; name: string } | null;
    booking?: Booking;
}

// M07 live tracking — the LocationUpdated broadcast payload + polling fallback.
export interface TrackingPayload {
    lat: number;
    lng: number;
    heading: number | null;
    speed: number | null;
    accuracy: number | null;
    ts: string | null;
}

export interface TrackingLast {
    booking_status: BookingStatus;
    session_status: 'active' | 'ended' | null;
    lat: number | null;
    lng: number | null;
    heading: number | null;
    speed: number | null;
    ts: string | null;
}

export interface TrackingConfig {
    ping_interval_seconds: number;
    min_move_meters: number;
    max_accuracy_meters: number;
    stale_after_seconds: number;
}

export interface ProviderProfile {
    id: number;
    bio: string | null;
    experience_years: number | null;
    base_lat: number | null;
    base_lng: number | null;
    service_radius_km: number;
    working_hours: WorkingHours | null;
    is_online: boolean;
    approval_status: ProviderApprovalStatus;
    approval_note: string | null;
    is_complete: boolean;
    rating_avg: number;
    rating_count: number;
    jobs_completed: number;
    categories?: { id: number; name: string }[];
    documents?: ProviderDocument[];
    blackouts?: ProviderBlackout[];
    user?: { id: number; name: string; email: string; phone: string | null };
}

// M08 payments + wallet.

/** Who settled the money — mirrors App\Domain\Payments\Enums\PaymentProvider. */
export type PaymentProvider = 'razorpay' | 'stripe' | 'cash' | 'wallet';

/** Mirrors App\Domain\Payments\Enums\PaymentState. */
export type PaymentState = 'initiated' | 'captured' | 'failed' | 'refunded';

export interface Payment {
    id: number;
    gateway: PaymentProvider;
    gateway_ref: string | null;
    amount: string;
    currency: string;
    status: PaymentState;
    captured_at: string | null;
    created_at: string | null;
}

export type WalletDirection = 'credit' | 'debit';

export interface WalletTransaction {
    id: number;
    type: string;
    direction: WalletDirection;
    amount: string;
    balance_after: string;
    note: string | null;
    created_at: string;
}

/** What the pay page may offer right now: configured gateways + the wallet. */
export interface PayMethods {
    gateways: PaymentProvider[];
    wallet: {
        enabled: boolean;
        balance: string;
        sufficient: boolean;
    };
}

/** Razorpay `orders` session — key_id is the publishable half of the pair. */
export interface RazorpaySession {
    key_id: string;
    order_id: string;
    amount: number;
    currency: string;
}

/** Stripe Checkout session: a hosted URL we redirect to. */
export interface StripeSession {
    url: string;
    publishable_key: string;
}

// M09 commission, earnings + payouts.

/** Mirrors App\Domain\Earnings\Enums\EarningType. */
export type EarningType = 'job' | 'reversal' | 'adjustment';

/** Mirrors App\Domain\Earnings\Enums\EarningStatus. */
export type EarningStatus = 'pending' | 'available' | 'paid_out';

/** Mirrors App\Domain\Earnings\Enums\PayoutStatus. */
export type PayoutStatus = 'requested' | 'approved' | 'paid' | 'rejected';

/**
 * One row of the provider's append-only ledger. `net` is a signed decimal
 * string: a cash job is negative because the provider already took the money.
 */
export interface Earning {
    id: number;
    type: EarningType;
    booking_code: string | null;
    gross: string;
    commission: string;
    net: string;
    commission_rate: string;
    status: EarningStatus;
    available_at: string | null;
    created_at: string | null;
}

/** Signed totals across the whole ledger — `claimable` is what a payout is worth now. */
export interface EarningsSummary {
    gross: number;
    commission: number;
    net: number;
    pending: number;
    available: number;
    paid_out: number;
    claimable: number;
}

export interface PayoutRequestRow {
    id: number;
    amount: string;
    status: PayoutStatus;
    reference: string | null;
    note: string | null;
    created_at: string | null;
    processed_at: string | null;
}

/** Bank details the provider supplied; shape depends on `method`. */
export interface PayoutMethodDetails {
    method: 'upi' | 'bank';
    upi_id?: string;
    account_name?: string;
    account_number?: string;
    ifsc?: string;
}

export interface AdminPayoutRow extends PayoutRequestRow {
    provider: { id: number | null; name: string | null; email: string | null };
    method_details: PayoutMethodDetails;
    earnings_count: number | null;
    processed_by: string | null;
}
