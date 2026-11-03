<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProfileRequest extends FormRequest
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
            'first_name' => ['sometimes', 'string', 'max:50'],
            'last_name' => ['sometimes', 'string', 'max:50'],

            // Facebook's intro line is capped at 101 characters.
            'bio' => ['sometimes', 'nullable', 'string', 'max:101'],
            'gender' => ['sometimes', 'nullable', 'string', 'in:female,male,custom,prefer_not_to_say'],
            'pronoun' => ['sometimes', 'nullable', 'string', 'max:30'],

            'headline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'about' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'current_city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'hometown' => ['sometimes', 'nullable', 'string', 'max:120'],
            'relationship_status' => ['sometimes', 'nullable', 'string',
                'in:single,in_a_relationship,engaged,married,divorced,widowed,complicated,separated'],
            'partner_id' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'favorite_quotes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'pronunciation' => ['sometimes', 'nullable', 'string', 'max:120'],

            'work' => ['sometimes', 'nullable', 'array', 'max:20'],
            'work.*.employer' => ['required_with:work', 'string', 'max:160'],
            'work.*.position' => ['nullable', 'string', 'max:160'],
            'work.*.city' => ['nullable', 'string', 'max:120'],
            'work.*.description' => ['nullable', 'string', 'max:1000'],
            'work.*.start_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'work.*.end_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],

            'education' => ['sometimes', 'nullable', 'array', 'max:20'],
            'education.*.school' => ['required_with:education', 'string', 'max:160'],
            'education.*.degree' => ['nullable', 'string', 'max:160'],
            'education.*.field' => ['nullable', 'string', 'max:160'],
            'education.*.year' => ['nullable', 'integer', 'min:1900', 'max:2100'],

            'places_lived' => ['sometimes', 'nullable', 'array', 'max:20'],
            'contact_info' => ['sometimes', 'nullable', 'array'],
            'basic_info' => ['sometimes', 'nullable', 'array'],
            'family_members' => ['sometimes', 'nullable', 'array'],
            'life_events' => ['sometimes', 'nullable', 'array'],

            'locale' => ['sometimes', 'string', 'in:en,ar,fr,es'],
            'timezone' => ['sometimes', 'timezone:all'],
        ];
    }
}
