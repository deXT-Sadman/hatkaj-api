<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Fall back to the account's profile for contact details the client omits.
     */
    protected function prepareForValidation(): void
    {
        $user = $this->user();

        $this->mergeIfMissing([
            'customer_name' => $user?->name,
            'customer_phone' => $user?->phone,
            'address' => $user?->default_address,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-]{7,20}$/'],
            'address' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'payment_method' => ['sometimes', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'service_id.exists' => 'The selected service is not available.',
            'customer_phone.regex' => 'The phone number must be 7-20 digits and may start with +.',
            'scheduled_at.after' => 'The booking time must be in the future.',
        ];
    }
}
