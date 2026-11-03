<?php

declare(strict_types=1);

namespace App\Http\Requests\Comment;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCommentRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:8000'],
            'parent_id' => ['nullable', 'uuid', 'exists:comments,id'],
            'media' => ['nullable', 'file', 'max:30720', 'mimes:jpg,jpeg,png,gif,webp'],
        ];
    }
}
