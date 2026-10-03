<?php

namespace App\Http\Requests;

use App\Models\ReminderRecipient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReminderRecipientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        if (is_string($email)) {
            $this->merge(['email' => strtolower(trim($email))]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:254', Rule::unique('reminder_recipients', 'email')],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $email = $this->input('email');
            if (! $validator->errors()->has('email') && is_string($email)
                && ReminderRecipient::isClientContactEmail($email)) {
                $validator->errors()->add('email', 'Client contact addresses cannot receive internal reminders.');
            }
        }];
    }
}
