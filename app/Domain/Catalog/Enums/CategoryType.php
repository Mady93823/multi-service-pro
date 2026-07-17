<?php

namespace App\Domain\Catalog\Enums;

/**
 * Which storefront surface a category is listed on. Everything else —
 * services, zones, cart, checkout, dispatch — is identical for both.
 */
enum CategoryType: string
{
    case Service = 'service';
    case Event = 'event';
}
