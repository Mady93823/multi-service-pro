<?php

namespace App\Domain\Tracking\Enums;

enum TrackingSessionStatus: string
{
    case Active = 'active';
    case Ended = 'ended';
}
