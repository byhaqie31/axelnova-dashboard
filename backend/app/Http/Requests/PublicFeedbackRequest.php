<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The client-facing submit on /feedback/{token}. Overall is the only required
 * score; the dimensions and NPS are optional so a hurried client can still
 * leave something useful. Attribution is only demanded once they opt in to
 * being published on the wall.
 */
class PublicFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the unguessable token is the credential
    }

    public function rules(): array
    {
        return [
            'overall' => ['required', 'integer', 'between:1,5'],
            'rating_design' => ['nullable', 'integer', 'between:1,5'],
            'rating_communication' => ['nullable', 'integer', 'between:1,5'],
            'rating_delivery' => ['nullable', 'integer', 'between:1,5'],
            'rating_value' => ['nullable', 'integer', 'between:1,5'],
            'nps' => ['nullable', 'integer', 'between:0,10'],
            'praise' => ['nullable', 'string', 'max:2000'],
            'improve' => ['nullable', 'string', 'max:2000'],
            'publish_consent' => ['boolean'],
            'attribution_name' => [
                Rule::requiredIf(fn () => $this->boolean('publish_consent')),
                'nullable', 'string', 'max:255',
            ],
            'attribution_role' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'attribution_name.required' => 'Add the name to publish under (or turn off the publish permission).',
        ];
    }
}
