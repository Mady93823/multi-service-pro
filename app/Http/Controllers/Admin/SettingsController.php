<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Settings\Actions\SaveSettingsGroup;
use App\Domain\Settings\SettingsGroupRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsGroupRegistry $groups) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.settings.edit', $this->groups->default()->key());
    }

    public function edit(string $group): Response
    {
        $settingsGroup = $this->groups->get($group);

        abort_if($settingsGroup === null, 404);

        return Inertia::render('admin/settings/edit', [
            'group' => $settingsGroup->key(),
            'title' => $settingsGroup->label(),
            'description' => $settingsGroup->description(),
            'groups' => $this->groups->navigation(),
            'values' => $settingsGroup->values(),
        ]);
    }

    public function update(
        UpdateSettingsRequest $request,
        string $group,
        SaveSettingsGroup $action,
        ActivityLogger $activity,
    ): RedirectResponse {
        $settingsGroup = $request->group();

        /** @var array<string, mixed> $data */
        $data = $request->safe()->except(['logo']);

        $logo = $request->file('logo');
        $files = $logo instanceof UploadedFile ? ['logo' => $logo] : [];

        $action->handle($settingsGroup, $data, $files);

        // Keys only — settings values include gateway secrets and must never
        // land in the activity log.
        $activity->log($request->user(), 'settings.update', null, [
            'group' => $settingsGroup->key(),
            'keys' => array_keys($data),
        ]);

        return back()->with('success', __('Settings saved.'));
    }
}
