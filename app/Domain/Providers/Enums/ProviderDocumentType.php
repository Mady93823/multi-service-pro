<?php

namespace App\Domain\Providers\Enums;

enum ProviderDocumentType: string
{
    case IdProof = 'id_proof';
    case AddressProof = 'address_proof';
    case Certificate = 'certificate';
    case Photo = 'photo';

    /**
     * Types the provider must upload before admin approval.
     * Certificates stay optional — not every trade has one.
     */
    public function isRequired(): bool
    {
        return $this !== self::Certificate;
    }
}
