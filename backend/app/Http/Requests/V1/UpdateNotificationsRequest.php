<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'new_bookings' => ['required', 'boolean'],
            'new_messages' => ['required', 'boolean'],
            'gallery_activity' => ['required', 'boolean'],
            'payment_received' => ['required', 'boolean'],
        ];
    }
}
