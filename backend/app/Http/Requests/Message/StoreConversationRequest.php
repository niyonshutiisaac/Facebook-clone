<?php

declare(strict_types=1);

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;

final class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:private,group'],
            'user_id' => ['required_if:type,private', 'uuid', 'exists:users,id'],
            'member_ids' => ['required_if:type,group', 'array', 'min:1', 'max:250'],
            'member_ids.*' => ['uuid', 'exists:users,id'],
            'title' => ['required_if:type,group', 'nullable', 'string', 'max:160'],
            'avatar' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
            'message' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
