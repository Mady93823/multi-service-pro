<?php

namespace App\Domain\Blocks\Types;

use App\Domain\Blocks\Block;
use App\Domain\Blocks\BlockContext;
use App\Domain\Blocks\BlockField;
use App\Domain\Cms\MarkdownRenderer;

class RichTextBlock extends Block
{
    public function __construct(private readonly MarkdownRenderer $renderer) {}

    public function type(): string
    {
        return 'rich_text';
    }

    public function label(): string
    {
        return __('Text');
    }

    public function fields(): array
    {
        return [
            BlockField::markdown('body', __('Body (markdown)')),
            BlockField::select('width', __('Width'), [
                ['value' => 'narrow', 'label' => __('Narrow (reading width)')],
                ['value' => 'wide', 'label' => __('Full width')],
            ], 'narrow'),
        ];
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:20000'],
            'width' => ['required', 'string', 'in:narrow,wide'],
        ];
    }

    public function data(array $payload, BlockContext $context): ?array
    {
        return [
            // The single markdown path (D20): raw HTML in the source is stripped,
            // so an admin cannot smuggle script into a public page.
            'html' => $this->renderer->render($this->text($payload, 'body')),
            'width' => $this->text($payload, 'width', 'narrow'),
        ];
    }
}
