<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:200'],
            'phone' => ['nullable', 'string', 'max:30'],
            'company' => ['nullable', 'string', 'max:200'],
            'project_type' => ['nullable', 'string', 'max:60'],
            'budget_hint' => ['nullable', 'string', 'max:100'],
            'timeline_hint' => ['nullable', 'string', 'max:100'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website_url' => ['nullable', 'max:0'], // honeypot — must be empty
        ];
    }
}
