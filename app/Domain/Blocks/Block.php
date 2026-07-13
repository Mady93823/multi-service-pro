<?php

namespace App\Domain\Blocks;

/**
 * A block type (M20, ADR D22).
 *
 * One class declares everything about a block: its schema (which drives the
 * admin form), its validation rules (enforced on write, so a payload the
 * renderer cannot handle never reaches the database) and its data (resolved on
 * the server, per request).
 *
 * `data()` may return models — the HTTP layer maps them to resources. The
 * domain stays free of HTTP, and a block never duplicates a wire shape that
 * already exists.
 */
abstract class Block
{
    abstract public function type(): string;

    abstract public function label(): string;

    /**
     * @return list<BlockField>
     */
    abstract public function fields(): array;

    /**
     * Validation rules for the payload, relative to it: `heading`, not
     * `payload.heading`. The request prefixes them.
     *
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * Props for the React renderer. **Returning null drops the block** — that
     * is how an embed pointing at a host we no longer allow degrades to a gap
     * instead of a broken iframe.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    abstract public function data(array $payload, BlockContext $context): ?array;

    /**
     * The payload a freshly added block starts with.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $defaults = [];

        foreach ($this->fields() as $field) {
            $defaults[$field->name] = $field->default;
        }

        return $defaults;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => $this->type(),
            'label' => $this->label(),
            'fields' => array_map(fn (BlockField $field): array => $field->toArray(), $this->fields()),
            'defaults' => $this->defaults(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function text(array $payload, string $key, string $fallback = ''): string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function nullableText(array $payload, string $key): ?string
    {
        $value = $this->text($payload, $key);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function number(array $payload, string $key, int $fallback): int
    {
        $value = $payload[$key] ?? null;

        return is_numeric($value) ? (int) $value : $fallback;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function flag(array $payload, string $key, bool $fallback = false): bool
    {
        $value = $payload[$key] ?? null;

        return is_bool($value) ? $value : $fallback;
    }

    /**
     * Rows of a repeater field, with every row guaranteed to be an array.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    protected function rows(array $payload, string $key): array
    {
        $rows = $payload[$key] ?? [];

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, 'is_array'));
    }
}
