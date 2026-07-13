<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Media\Actions\AttachLibraryAsset;
use App\Models\MediaAsset;
use App\Models\Popup;

class SavePopup
{
    public function __construct(private readonly AttachLibraryAsset $attach) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?MediaAsset $asset = null, ?Popup $popup = null): Popup
    {
        if ($popup === null) {
            $popup = Popup::query()->create($data);
        } else {
            $popup->update($data);
        }

        if ($asset !== null) {
            $this->attach->handle($popup, $asset, 'image');
        }

        return $popup;
    }
}
