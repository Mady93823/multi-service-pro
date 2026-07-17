<?php

namespace App\Domain\Bookings;

use App\Models\Service;
use App\Models\ServiceAddon;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Session-backed cart (02-Modules M04): guests build a cart anonymously and
 * keep it through login because Laravel migrates the same session — no cart
 * table needed. Lines are keyed by service + chosen add-on set, so the same
 * service with different add-ons stays on separate lines.
 *
 * @phpstan-type CartLine array{key: string, service_id: int, qty: int, addon_ids: list<int>}
 * @phpstan-type DetailedLine array{key: string, service: Service, qty: int, addons: Collection<int, ServiceAddon>, unit_total: string, line_total: string}
 */
class CartManager
{
    private const SESSION_KEY = 'cart.items';

    private const COUPON_KEY = 'cart.coupon';

    public const MAX_QTY = 10;

    public function __construct(private readonly Session $session) {}

    /**
     * @param  list<int>  $addonIds
     */
    public function add(int $serviceId, int $qty, array $addonIds = []): void
    {
        $addonIds = array_values(array_unique(array_map(intval(...), $addonIds)));
        sort($addonIds);

        $key = $this->lineKey($serviceId, $addonIds);
        $lines = $this->lines();

        if (isset($lines[$key])) {
            $lines[$key]['qty'] = min($lines[$key]['qty'] + $qty, self::MAX_QTY);
        } else {
            $lines[$key] = [
                'key' => $key,
                'service_id' => $serviceId,
                'qty' => min($qty, self::MAX_QTY),
                'addon_ids' => $addonIds,
            ];
        }

        $this->persist($lines);
    }

    public function updateQty(string $key, int $qty): void
    {
        $lines = $this->lines();

        if (! isset($lines[$key])) {
            return;
        }

        if ($qty < 1) {
            unset($lines[$key]);
        } else {
            $lines[$key]['qty'] = min($qty, self::MAX_QTY);
        }

        $this->persist($lines);
    }

    public function remove(string $key): void
    {
        $lines = $this->lines();
        unset($lines[$key]);
        $this->persist($lines);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
        $this->session->forget(self::COUPON_KEY);
    }

    /**
     * Coupon code the customer applied at checkout (M12). Session-only,
     * like the cart itself — eligibility is re-checked at placement.
     */
    public function setCouponCode(?string $code): void
    {
        if ($code === null) {
            $this->session->forget(self::COUPON_KEY);

            return;
        }

        $this->session->put(self::COUPON_KEY, strtoupper(trim($code)));
    }

    public function couponCode(): ?string
    {
        $code = $this->session->get(self::COUPON_KEY);

        return is_string($code) && $code !== '' ? $code : null;
    }

    /**
     * Number of cart lines (header badge).
     */
    public function count(): int
    {
        return count($this->lines());
    }

    /**
     * How many of one service the cart holds, across every line. The same
     * service with different add-ons sits on separate lines, and the service
     * page asks about the service, not one add-on combination.
     */
    public function qtyForService(int $serviceId): int
    {
        return array_sum(array_map(
            fn (array $line): int => $line['service_id'] === $serviceId ? $line['qty'] : 0,
            $this->lines(),
        ));
    }

    /**
     * Lines resolved against the live catalog. Services or add-ons that were
     * deactivated or deleted since they were added are silently dropped and
     * the session is cleaned up.
     *
     * @return list<DetailedLine>
     */
    public function detailed(): array
    {
        $lines = $this->lines();

        if ($lines === []) {
            return [];
        }

        $services = Service::query()
            ->active()
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->with(['category', 'media', 'addons' => fn ($query) => $query->where('is_active', true)])
            ->findMany(array_column($lines, 'service_id'))
            ->keyBy('id');

        $detailed = [];
        $clean = [];

        foreach ($lines as $key => $line) {
            $service = $services->get($line['service_id']);

            if ($service === null) {
                continue; // service gone or deactivated — drop the line
            }

            $addons = $service->addons->whereIn('id', $line['addon_ids'])->values();
            $addonIds = $addons->pluck('id')->sort()->values()->all();

            $unit = (float) $service->price + (float) $addons->sum(fn (ServiceAddon $addon): float => (float) $addon->price);

            $detailed[] = [
                'key' => $key,
                'service' => $service,
                'qty' => $line['qty'],
                'addons' => $addons,
                'unit_total' => number_format($unit, 2, '.', ''),
                'line_total' => number_format($unit * $line['qty'], 2, '.', ''),
            ];

            $clean[$key] = [...$line, 'addon_ids' => $addonIds];
        }

        if (count($clean) !== count($lines)) {
            $this->persist($clean);
        }

        return $detailed;
    }

    /**
     * Cart services the given zone cannot book (zone-restricted services
     * whose zone list does not include it). Null zone = default address is
     * outside every service area, so every restricted service is blocked.
     *
     * @param  list<DetailedLine>  $detailed
     * @return list<string> service names
     */
    public function blockedServiceNames(array $detailed, ?int $zoneId): array
    {
        $blocked = [];

        foreach ($detailed as $line) {
            if (! $line['service']->zones()->exists()) {
                continue;
            }

            if ($zoneId === null || ! $line['service']->zones()->whereKey($zoneId)->exists()) {
                $blocked[] = $line['service']->name;
            }
        }

        return $blocked;
    }

    /**
     * @return array<string, CartLine>
     */
    private function lines(): array
    {
        /** @var array<string, CartLine> */
        return $this->session->get(self::SESSION_KEY, []);
    }

    /**
     * @param  array<string, CartLine>  $lines
     */
    private function persist(array $lines): void
    {
        $this->session->put(self::SESSION_KEY, $lines);
    }

    /**
     * @param  list<int>  $addonIds
     */
    private function lineKey(int $serviceId, array $addonIds): string
    {
        return substr(md5($serviceId.':'.implode(',', $addonIds)), 0, 12);
    }
}
