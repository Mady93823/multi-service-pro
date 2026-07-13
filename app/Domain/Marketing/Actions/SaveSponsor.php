<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Media\Actions\AttachLibraryAsset;
use App\Models\MediaAsset;
use App\Models\Sponsor;

class SaveSponsor
{
    public function __construct(private readonly AttachLibraryAsset $attach) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?MediaAsset $asset = null, ?Sponsor $sponsor = null): Sponsor
    {
        if ($sponsor === null) {
            $sponsor = Sponsor::query()->create($data);
        } else {
            $sponsor->update($data);
        }

        if ($asset !== null) {
            $this->attach->handle($sponsor, $asset, 'logo');
        }

        return $sponsor;
    }
}
