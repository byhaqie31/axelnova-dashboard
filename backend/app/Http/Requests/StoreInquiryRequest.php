<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'origin' => ['nullable', 'in:quote,contact,partner,collaboration'],
            'subject' => ['nullable', 'string', 'max:60'],
            // origin=partner: a client hiring a studio partner through Axel Nova — the live partner's slug.
            'partner' => ['exclude_unless:origin,partner', 'required', 'string', 'max:80',
                Rule::exists('collaboration_partners', 'slug')->where('active', true)->whereNull('deleted_at')],
            // origin=collaboration: the applying studio's work (folded into the message — no column).
            'portfolio_url' => ['exclude_unless:origin,collaboration', 'nullable', 'url:http,https', 'max:300'],
            'website_url' => ['nullable', 'max:0'], // honeypot — must be empty
        ];
    }
}
