<?php

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Groups\SettingsGroup;
use App\Domain\Settings\SettingsRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Writes one settings group (ADR D24). Replaces the old UpdateSettings action,
 * which wrote every key on every save.
 */
class SaveSettingsGroup
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * @param  array<string, mixed>  $data  validated payload for this group only
     * @param  array<string, UploadedFile>  $files
     */
    public function handle(SettingsGroup $group, array $data, array $files = []): void
    {
        DB::transaction(fn () => $group->apply($data, $files));

        $this->settings->flush();
    }
}
