<?php

namespace App\Domain\Dispatch\Enums;

/**
 * How eligible providers are offered a job (M06). `manual` is stamped on the
 * offer row when an admin assigns directly, so history reads consistently.
 */
enum DispatchMode: string
{
    case Nearest = 'nearest';
    case Broadcast = 'broadcast';
    case Manual = 'manual';
}
