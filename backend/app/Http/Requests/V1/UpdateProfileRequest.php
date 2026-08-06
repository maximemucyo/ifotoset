<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge([
                'username' => $this->username ? strtolower(trim($this->username)) : null,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $reservedUsernames = [
            'admin', 'api', 'studio', 'settings', 'gallery',
            'galleries', 'bookings', 'login', 'register',
            'public', 'support', 'pricing', 'dashboard', 'trash'
        ];

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'username' => [
                'nullable',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('users')->ignore($this->user()->id),
                Rule::notIn($reservedUsernames),
            ],
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'The username may only contain lowercase letters, numbers, and hyphens.',
            'username.not_in' => 'This username is reserved and cannot be used.',
        ];
    }
}
