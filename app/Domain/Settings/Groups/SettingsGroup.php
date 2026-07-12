<?php

namespace App\Domain\Settings\Groups;

use App\Domain\Settings\SettingsRegistry;
use Illuminate\Http\UploadedFile;

/**
 * One editable settings screen (ADR D24).
 *
 * A group owns a disjoint slice of the settings keys and is the only thing that
 * validates or writes them. The old design validated every key on every save,
 * so adding one required key 422'd every unrelated form; here a save composes
 * the rules of exactly one group, and `SettingsGroupCoverageTest` proves no key
 * is owned twice or forgotten.
 *
 * A group's *form field* names are not its settings keys (`payment_timeout_minutes`
 * writes `booking.payment_timeout_minutes`) — the mapping lives in apply().
 */
abstract class SettingsGroup
{
    public function __construct(protected readonly SettingsRegistry $settings) {}

    /** URL segment: /admin/settings/{key}. */
    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function description(): string;

    /**
     * The settings keys this group owns.
     *
     * @return list<string>
     */
    abstract public function keys(): array;

    /**
     * Validation rules for this group's form fields only.
     *
     * Takes the raw input array rather than a Request: the domain layer stays
     * HTTP-free (arch rule), and a conditional rule only ever needs the values.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    abstract public function rules(array $input): array;

    /**
     * Props for the screen. Secrets go out as `*_set` booleans — never values:
     * Inertia serializes every prop into the page HTML (M08).
     *
     * @return array<string, mixed>
     */
    abstract public function values(): array;

    /**
     * @param  array<string, mixed>  $data  validated payload
     * @param  array<string, UploadedFile>  $files  uploaded files, by field name
     */
    abstract public function apply(array $data, array $files = []): void;

    /**
     * An unchecked switch is simply absent from the payload, so a missing key
     * means false — never "keep the old value".
     *
     * @param  array<string, mixed>  $data
     */
    protected function toggle(array $data, string $field): bool
    {
        return (bool) ($data[$field] ?? false);
    }
}
