<?php

namespace App\Http\Requests\Admin;

use App\Domain\Blocks\BlockRegistry;
use App\Models\PageBlock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A block's payload is validated against the schema of *its own type* (D22), so
 * a shape the renderer cannot handle never reaches the database.
 *
 * On update the type comes from the stored block, never from the payload — a
 * payload only means anything to the block that validated it.
 */
class SavePageBlockRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $registry = app(BlockRegistry::class);
        $existing = $this->route('block');
        $editing = $existing instanceof PageBlock;

        $type = $editing ? $existing->type : $this->string('type')->toString();

        $rules = [
            'type' => [$editing ? 'sometimes' : 'required', 'string', Rule::in($registry->types())],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'payload' => ['sometimes', 'array'],
        ];

        $block = $registry->find($type);

        if ($block === null) {
            // Unknown type — the `in` rule above rejects it; there is no schema
            // to validate the payload against.
            return $rules;
        }

        foreach ($block->rules() as $key => $rule) {
            $rules['payload.'.$key] = $rule;
        }

        return $rules;
    }
}
