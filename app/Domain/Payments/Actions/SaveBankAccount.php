<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Media\Actions\AttachLibraryAsset;
use App\Models\BankAccount;
use App\Models\MediaAsset;

class SaveBankAccount
{
    public function __construct(private readonly AttachLibraryAsset $attach) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?MediaAsset $asset = null, ?BankAccount $account = null): BankAccount
    {
        if ($account === null) {
            $account = BankAccount::query()->create($data);
        } else {
            $account->update($data);
        }

        // Picking copies the file into this account's own collection (D29).
        if ($asset !== null) {
            $this->attach->handle($account, $asset, 'qr');
        }

        return $account;
    }
}
