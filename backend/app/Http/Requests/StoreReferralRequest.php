<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'referrer_name' => ['required', 'string', 'min:2', 'max:150'],
            'referrer_email' => ['required', 'email:rfc', 'max:200'],
            'referrer_phone' => ['nullable', 'string', 'max:30'],
            'business_name' => ['required', 'string', 'min:2', 'max:200'],
            'business_contact_name' => ['nullable', 'string', 'max:150'],
            'business_email' => ['nullable', 'email:rfc', 'max:200'],
            'business_phone' => ['nullable', 'string', 'max:30'],
            'relationship_tier' => ['required', 'string', 'in:cold,warm,closed'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'agreed_terms' => ['accepted'],
            'website_url' => ['nullable', 'max:0'], // honeypot — must be empty
        ];
    }
}
