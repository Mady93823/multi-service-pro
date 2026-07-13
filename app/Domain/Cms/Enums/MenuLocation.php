<?php

namespace App\Domain\Cms\Enums;

/**
 * Where a menu is rendered. A location holds at most one menu — the storefront
 * asks for a location, never for a menu id.
 */
enum MenuLocation: string
{
    case Header = 'header';
    case FooterOne = 'footer_1';
    case FooterTwo = 'footer_2';
    case FooterThree = 'footer_3';

    public function label(): string
    {
        return match ($this) {
            self::Header => __('Header'),
            self::FooterOne => __('Footer column 1'),
            self::FooterTwo => __('Footer column 2'),
            self::FooterThree => __('Footer column 3'),
        };
    }

    public function isFooter(): bool
    {
        return $this !== self::Header;
    }
}
