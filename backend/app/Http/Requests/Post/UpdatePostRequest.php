<?php

declare(strict_types=1);

namespace App\Http\Requests\Post;

use App\Enums\Audience;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePostRequest extends FormRequest
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
            'body' => ['nullable', 'string', 'max:63206'],
            'audience' => ['nullable', 'string', 'in:'.implode(',', array_column(Audience::cases(), 'value'))],
            'audience_user_ids' => ['nullable', 'array'],
            'audience_user_ids.*' => ['uuid', 'exists:users,id'],
            'background' => ['nullable', 'string', 'max:40'],
            'feeling' => ['nullable', 'string', 'max:60'],
            'activity' => ['nullable', 'string', 'max:120'],
            'location_name' => ['nullable', 'string', 'max:160'],
            'comments_enabled' => ['nullable', 'boolean'],
        ];
    }
}
