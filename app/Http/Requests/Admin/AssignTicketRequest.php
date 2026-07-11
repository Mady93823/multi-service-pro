<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssignTicketRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // null unassigns.
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $id = $this->input('assigned_to');

                if ($id === null) {
                    return;
                }

                $assignee = User::query()->find($id);

                if ($assignee === null || ! $assignee->hasRole('admin')) {
                    $validator->errors()->add('assigned_to', __('Tickets can only be assigned to an admin.'));
                }
            },
        ];
    }

    public function assignee(): ?User
    {
        $id = $this->validated('assigned_to');

        return $id === null ? null : User::query()->findOrFail((int) $id);
    }
}
