<?php

namespace App\Http\Requests;

use App\Services\WhatsAppPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('whatsapp_phone')) {
            try {
                $this->merge(['whatsapp_phone' => app(WhatsAppPhone::class)->normalize((string) $this->input('whatsapp_phone'))]);
            } catch (InvalidArgumentException) {
                // Validation below reports the invalid input.
            }
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255', 'regex:/^(?=(?:\D*\d){7,20}\D*$)\+?(?:\d+|\(\d+\))(?:[ .-]*(?:\d+|\(\d+\)))*(?:\s*(?:x|ext\.?)\s*\d+)?$/i'],
            'whatsapp_phone' => [Rule::requiredIf($this->boolean('whatsapp_reminders_enabled')), 'nullable', 'string', 'max:24', function (string $attribute, mixed $value, \Closure $fail): void {
                try {
                    app(WhatsAppPhone::class)->normalize((string) $value);
                } catch (InvalidArgumentException) {
                    $fail('Enter a valid international WhatsApp number beginning with +.');
                }
            }],
            'whatsapp_reminders_enabled' => ['sometimes', 'boolean'],
            'email' => ['nullable', 'email', 'max:255'],
            'billing_address' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a phone number with 7 to 20 digits using common separators or an extension.',
        ];
    }
}
