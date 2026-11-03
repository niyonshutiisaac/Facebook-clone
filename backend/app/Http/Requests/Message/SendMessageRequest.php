<?php

declare(strict_types=1);

namespace App\Http\Requests\Message;

use App\Enums\MessageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SendMessageRequest extends FormRequest
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
            'body' => ['nullable', 'string', 'max:20000'],
            'type' => ['nullable', 'string', 'in:'.implode(',', array_column(MessageType::cases(), 'value'))],
            'reply_to_id' => ['nullable', 'uuid', 'exists:messages,id'],
            'forwarded_from_id' => ['nullable', 'uuid', 'exists:messages,id'],
            'upload' => ['nullable', 'file', 'max:102400', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,webm,mp3,wav,pdf,zip,doc,docx,txt'],
        ];
    }

    /**
     * A message must carry something: text, an attachment or a GIF.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if (! filled($this->input('body')) && ! $this->hasFile('upload') && ! filled($this->input('gif_url'))) {
                $v->errors()->add('body', 'Write a message, or attach a photo or file.');
            }
        });
    }
}
