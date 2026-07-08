<?php

namespace App\Domain\Providers\Enums;

enum ProviderDocumentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
