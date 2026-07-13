<?php

namespace App\Domain\Blocks;

/**
 * One field in a block's admin form (M20).
 *
 * The schema is declared once, in the block, and the admin form is *rendered*
 * from it — twelve block types do not get twelve hand-written forms, which is
 * twelve places to forget a field.
 */
final class BlockField
{
    /**
     * @param  list<array{value: string, label: string}>  $options
     * @param  list<BlockField>  $fields  sub-fields of a repeater row
     */
    private function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $label,
        public readonly mixed $default = null,
        public readonly array $options = [],
        public readonly array $fields = [],
        public readonly ?string $help = null,
    ) {}

    public static function text(string $name, string $label, string $default = '', ?string $help = null): self
    {
        return new self($name, 'text', $label, $default, help: $help);
    }

    public static function textarea(string $name, string $label, string $default = '', ?string $help = null): self
    {
        return new self($name, 'textarea', $label, $default, help: $help);
    }

    public static function markdown(string $name, string $label, string $default = '', ?string $help = null): self
    {
        return new self($name, 'markdown', $label, $default, help: $help);
    }

    public static function number(string $name, string $label, int $default = 0, ?string $help = null): self
    {
        return new self($name, 'number', $label, $default, help: $help);
    }

    public static function toggle(string $name, string $label, bool $default = false, ?string $help = null): self
    {
        return new self($name, 'toggle', $label, $default, help: $help);
    }

    /**
     * @param  list<array{value: string, label: string}>  $options
     */
    public static function select(string $name, string $label, array $options, string $default = '', ?string $help = null): self
    {
        return new self($name, 'select', $label, $default, options: $options);
    }

    /**
     * A library picture. The field is always called `media_asset_id` inside its
     * row — that name is what SaveBlock looks for when it copies the file in.
     */
    public static function media(string $label, ?string $help = null): self
    {
        return new self('media_asset_id', 'media', $label, null, help: $help);
    }

    /**
     * @param  list<BlockField>  $fields
     */
    public static function repeater(string $name, string $label, array $fields, ?string $help = null): self
    {
        return new self($name, 'repeater', $label, [], fields: $fields, help: $help);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'label' => $this->label,
            'default' => $this->default,
            'options' => $this->options,
            'fields' => array_map(fn (BlockField $field): array => $field->toArray(), $this->fields),
            'help' => $this->help,
        ];
    }
}
