<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:50', 'regex:/^[\p{L}\p{M}\' -]+$/u'],
            'last_name' => ['required', 'string', 'max:50', 'regex:/^[\p{L}\p{M}\' -]+$/u'],
            'email' => ['required_without:phone', 'nullable', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:30', 'unique:users,phone', 'regex:/^\+?[0-9\s()-]{7,30}$/'],
            'password' => [
                'required', 'string', 'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->uncompromised(),
            ],

            // Facebook's rule: 13 is the minimum age.
            'birth_date' => [
                'required', 'date_format:Y-m-d', 'before:today',
                'before_or_equal:'.now()->subYears((int) config('auth.minimum_age', 13))->toDateString(),
            ],
            'gender' => ['nullable', 'string', 'in:female,male,custom,prefer_not_to_say'],
            'locale' => ['nullable', 'string', 'in:en,ar,fr,es'],
            'timezone' => ['nullable', 'timezone:all'],
            'username' => ['nullable', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9.]+$/', 'unique:users,username'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'birth_date.before_or_equal' => 'You must be at least '.(int) config('auth.minimum_age', 13).' years old to create an account.',
            'birth_date.before' => 'Enter a valid date of birth.',
            'email.required_without' => 'Enter an email address or a phone number.',
            'phone.required_without' => 'Enter an email address or a phone number.',
            'password.uncompromised' => 'This password has appeared in a data breach. Please choose another.',
        ];
    }
}
