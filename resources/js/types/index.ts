import { type FormDataConvertible } from '@inertiajs/core';
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
    /**
     * A collapsible group (M17). A group's `url` is the trail it owns — it is
     * matched against the current URL to auto-expand, and is not itself a link.
     */
    children?: NavItem[];
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
    /** Non-null while an admin is browsing as someone else (M13). */
    impersonation: { user_name: string } | null;
    /** Published pages flagged for the storefront footer (M14, white-label). */
    footer_pages: { title: string; slug: string }[];
    /** Storefront chrome: menus, header/footer style, social, cookie, custom code (M19). */
    site: SiteContent;
    [key: string]: unknown;
}

/** A resolved menu link — the server dropped anything it could not resolve (M19). */
export interface SiteMenuLink {
    label: string;
    url: string;
    children: SiteMenuLink[];
}

export interface SiteAppearance {
    header_variant: 'classic' | 'centered' | 'minimal';
    sticky_header: boolean;
    footer_variant: 'columns' | 'simple';
    footer_about: string | null;
    copyright: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    contact_address: string | null;
    login_headline: string | null;
    login_subcopy: string | null;
    login_image_url: string | null;
}

export interface SitePopup {
    id: number;
    title: string;
    /** Sanitized HTML from the markdown source — the server is the only renderer (D20). */
    html: string | null;
    link_url: string | null;
    link_label: string | null;
    image_url: string | null;
    frequency_days: number;
}

export interface SiteContent {
    menus: Record<string, SiteMenuLink[]>;
    appearance: SiteAppearance;
    social: Record<string, string>;
    cookie: { message: string; accept_label: string; decline_label: string | null; policy_slug: string | null } | null;
    /** Admin-authored snippets — storefront shells only, null everywhere else (D26). */
    custom_code: { css: string | null; js: string | null } | null;
    newsletter: boolean;
    /** The live popup for this visitor; the audience was decided on the server (M19). */
    popup: SitePopup | null;
}

export interface Testimonial {
    id: number;
    name: string;
    role: string | null;
    quote: string;
    rating: number | null;
    sort_order: number;
    is_active: boolean;
    from_review: boolean;
    avatar_url: string | null;
}

export interface Sponsor {
    id: number;
    name: string;
    link_url: string | null;
    sort_order: number;
    is_active: boolean;
    logo_url: string | null;
}

export interface Popup {
    id: number;
    title: string;
    body: string | null;
    link_url: string | null;
    link_label: string | null;
    audience: string;
    audience_label: string;
    frequency_days: number;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
    image_url: string | null;
}

export interface CmsPage {
    id: number;
    title: string;
    slug: string;
    body: string;
    is_published: boolean;
    show_in_footer: boolean;
    sort_order: number;
    is_home: boolean;
    blocks_count?: number;
    updated_at: string | null;
}

export interface Faq {
    id: number;
    question: string;
    answer: string;
    is_active: boolean;
    sort_order: number;
}

export interface Language {
    id: number;
    code: string;
    name: string;
    native_name: string | null;
    is_active: boolean;
    is_default: boolean;
    is_site_locale: boolean;
    translated_count: number;
}

export interface TranslationEntry {
    key: string;
    source: string;
    value: string;
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

export interface Coupon {
    id: number;
    code: string;
    type: 'flat' | 'percent';
    value: string;
    max_discount: string | null;
    min_order: string | null;
    usage_limit: number | null;
    per_user_limit: number | null;
    first_order_only: boolean;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
    usages_count?: number;
    created_at: string | null;
}

export type BannerPlacement = 'home_hero' | 'home_strip';

export interface Banner {
    id: number;
    title: string;
    link_url: string | null;
    placement: BannerPlacement;
    sort_order: number;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
    image_url: string | null;
}

export interface ReferralEntry {
    id: number;
    referee_name: string | null;
    status: 'pending' | 'rewarded';
    reward_amount: string | null;
    created_at: string | null;
}

export interface ReferralCard {
    code: string;
    share_url: string;
    reward_amount: string;
    entries: ReferralEntry[];
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
export type PaymentProvider = 'razorpay' | 'stripe' | 'cash' | 'wallet' | 'offline';

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

// M22 payments hub: bank/UPI accounts, payout destinations, the admin list row.

/** M23 — the event × channel matrix. `database` and `broadcast` are always on and never appear here. */
export interface NotificationEventInfo {
    key: string;
    label: string;
    description: string;
}

export interface NotificationChannelInfo {
    key: string;
    label: string;
    /** False when the install has no SMTP / no SMS gateway / no Firebase — the switch would send nothing. */
    available: boolean;
}

export type NotificationMatrix = Record<string, Record<string, boolean>>;

/**
 * One switch, on its way back to the server. The index signature is not
 * decoration: Inertia's useForm types its data as FormDataConvertible, and a
 * plain interface is not assignable to it (M20's landmine).
 */
export interface NotificationPreferenceRow {
    [key: string]: FormDataConvertible;
    event: string;
    channel: string;
    enabled: boolean;
}

export interface BankAccount {
    id: number;
    label: string;
    account_name: string | null;
    account_number: string | null;
    ifsc: string | null;
    upi_id: string | null;
    notes: string | null;
    is_active: boolean;
    sort_order: number;
    qr_url: string | null;
    qr_thumb_url: string | null;
}

export interface PayoutAccount {
    id: number;
    type: 'upi' | 'bank';
    label: string | null;
    account_name: string | null;
    account_number: string | null;
    ifsc: string | null;
    upi_id: string | null;
    is_default: boolean;
    is_verified: boolean;
    verified_at: string | null;
}

/** One row of the admin payments list. */
export interface AdminPayment {
    id: number;
    gateway: PaymentProvider;
    status: PaymentState;
    amount: number;
    currency: string;
    reference: string | null;
    failure_reason: string | null;
    bank_account: string | null;
    has_proof: boolean;
    proof_url: string | null;
    booking: {
        id: number;
        code: string | null;
        customer: string | null;
    };
    created_at: string | null;
    captured_at: string | null;
}

/** What the pay page may offer right now: gateways, wallet, bank transfer. */
export interface PayMethods {
    gateways: PaymentProvider[];
    wallet: {
        enabled: boolean;
        balance: string;
        sufficient: boolean;
    };
    offline: {
        enabled: boolean;
        instructions: string;
        accounts: BankAccount[];
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
    /** The stored account it was requested against (M22); null for pre-M22 rows. */
    account: PayoutAccount | null;
    earnings_count: number | null;
    processed_by: string | null;
}

/** Admin dashboard KPI tiles (M13) — every figure is a snapshot-column aggregate. */
export interface DashboardTiles {
    bookings_today: number;
    bookings_week: number;
    gmv_month: number;
    commission_month: number;
    open_jobs: number;
    pending_payouts_count: number;
    pending_payouts_amount: number;
    providers_online: number;
    providers_pending_kyc: number;
}

export interface BookingsDayPoint {
    date: string;
    bookings: number;
}

export interface RevenueDayPoint {
    date: string;
    gross: number;
    commission: number;
}

export interface TopServiceRow {
    service: string;
    bookings: number;
    revenue: number;
}

export interface LeaderboardRow {
    id: number;
    name: string;
    jobs_completed: number;
    rating_avg: number;
    rating_count: number;
    gross: number;
    net: number;
}

/** One admin report's metadata (M13); labels arrive pre-translated. */
export interface ReportInfo {
    slug: string;
    title: string;
    columns: { key: string; label: string }[];
    status_options: string[];
}

export interface ReportFiltersState {
    from: string | null;
    to: string | null;
    status: string | null;
}

export type ReportRow = Record<string, string | number | null>;

export interface ActivityLogRow {
    id: number;
    actor: string | null;
    action: string;
    subject_type: string | null;
    subject_id: number | null;
    context: Record<string, unknown> | null;
    created_at: string;
}

// ---- Support & helpdesk (M16) ----

export type TicketStatus = 'open' | 'pending' | 'resolved' | 'closed';

export type TicketPriority = 'low' | 'normal' | 'high';

export type TicketCategory = 'booking' | 'payment' | 'account' | 'other';

export interface SupportTicket {
    id: number;
    code: string;
    subject: string;
    category: TicketCategory;
    category_label: string;
    priority: TicketPriority;
    priority_label: string;
    status: TicketStatus;
    status_label: string;
    resolution_note: string | null;
    last_reply_at: string | null;
    created_at: string | null;
    messages_count?: number;
    user?: { id: number; name: string; email: string };
    booking?: { id: number; code: string } | null;
    assignee?: { id: number; name: string } | null;
}

export interface TicketAttachment {
    id: number;
    name: string;
    size: number;
    url: string;
}

export interface SupportTicketMessage {
    id: number;
    body: string;
    is_staff: boolean;
    author_name: string | null;
    created_at: string | null;
    attachments: TicketAttachment[];
}

export interface CannedResponse {
    title: string;
    body: string;
}

/** A reusable image in the media library (M18). */
export interface MediaAsset {
    id: number;
    name: string;
    url: string | null;
    thumb_url: string | null;
    size: number;
    uploaded_by?: string | null;
    uploaded_at?: string | null;
    usage_count?: number;
}

/** A block on a page, resolved by the server (M20). `props` is whatever the block type declared. */
export interface RenderedBlock {
    id: number;
    type: string;
    props: Record<string, unknown>;
}

export interface BlockFieldSchema {
    name: string;
    type: 'text' | 'textarea' | 'markdown' | 'number' | 'toggle' | 'select' | 'media' | 'repeater';
    label: string;
    default: FormDataConvertible;
    options: { value: string; label: string }[];
    fields: BlockFieldSchema[];
    help: string | null;
}

/** A block's payload travels through Inertia's form helper, so it must be form-convertible. */
export type BlockPayload = Record<string, FormDataConvertible>;

export interface BlockSchema {
    type: string;
    label: string;
    fields: BlockFieldSchema[];
    defaults: BlockPayload;
}

/** One saved block, as the admin block editor sees it. */
export interface EditableBlock {
    id: number;
    type: string;
    label: string | null;
    payload: BlockPayload;
    is_active: boolean;
    starts_at: string | null;
    ends_at: string | null;
    image_urls: Record<number, string>;
}

/** A blog category (M21). */
export interface BlogCategory {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    sort_order: number;
    is_active: boolean;
    posts_count?: number;
}

/** A blog post (M21). `body` is the markdown source — admin screens only. */
export interface BlogPost {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    body?: string;
    tags: string[];
    is_featured: boolean;
    is_published: boolean;
    published_at: string | null;
    meta_title: string | null;
    meta_description: string | null;
    cover_url: string | null;
    cover_hero_url: string | null;
    category?: BlogCategory | null;
    author?: string | null;
}
