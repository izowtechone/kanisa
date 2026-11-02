<?php

namespace App\Http\Requests;

use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCommunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit communities') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:150'],
            'leader_member_id' => ['nullable', 'integer', 'exists:members,id'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('leader_member_id') || ! $this->filled('leader_member_id')) {
                    return;
                }

                $leaderIsActive = Member::query()
                    ->whereKey($this->integer('leader_member_id'))
                    ->where('status', 'active')
                    ->exists();

                if (! $leaderIsActive) {
                    $validator->errors()->add('leader_member_id', __('communities.leader_not_active'));
                }
            },
        ];
    }
}
