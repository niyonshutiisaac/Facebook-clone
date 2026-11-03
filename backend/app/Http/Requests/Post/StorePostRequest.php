<?php

declare(strict_types=1);

namespace App\Http\Requests\Post;

use App\Enums\Audience;
use App\Enums\PostType;
use Illuminate\Foundation\Http\FormRequest;

final class StorePostRequest extends FormRequest
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
        $audiences = implode(',', array_column(Audience::cases(), 'value'));
        $types = implode(',', array_column(PostType::cases(), 'value'));

        return [
            'body' => ['nullable', 'string', 'max:63206', 'required_without_all:media,poll,shared_post_id,gif_url'],
            'type' => ['nullable', 'string', 'in:'.$types],
            'audience' => ['nullable', 'string', 'in:'.$audiences],
            'audience_user_ids' => ['nullable', 'array', 'max:5000'],
            'audience_user_ids.*' => ['uuid', 'exists:users,id'],
            'background' => ['nullable', 'string', 'max:40'],

            'feeling' => ['nullable', 'string', 'max:60'],
            'feeling_emoji' => ['nullable', 'string', 'max:16'],
            'activity' => ['nullable', 'string', 'max:120'],
            'activity_emoji' => ['nullable', 'string', 'max:16'],

            'location_name' => ['nullable', 'string', 'max:160'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'life_event_type' => ['nullable', 'string', 'max:40'],

            'shared_post_id' => ['nullable', 'uuid', 'exists:posts,id'],
            'gif_url' => ['nullable', 'url', 'max:500'],

            'media' => ['nullable', 'array', 'max:10'],
            'media.*' => ['file', 'max:524288', 'mimes:jpg,jpeg,png,gif,webp,avif,mp4,mov,webm'],

            'poll' => ['nullable', 'array'],
            'poll.question' => ['required_with:poll', 'string', 'max:500'],
            'poll.options' => ['required_with:poll', 'array', 'min:2', 'max:10'],
            'poll.options.*' => ['required', 'string', 'max:300'],
            'poll.allows_multiple' => ['nullable', 'boolean'],
            'poll.closes_at' => ['nullable', 'date', 'after:now'],

            'group_id' => ['nullable', 'uuid', 'exists:groups,id'],
            'page_id' => ['nullable', 'uuid', 'exists:pages,id'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required_without_all' => 'Write something, or add a photo, video or poll.',
            'media.*.max' => 'Each file must be under 512 MB.',
            'media.max' => 'You can attach up to 10 files to one post.',
        ];
    }
}
