<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin edit / moderate. Status can arrive here too, but the controller runs
 * it through the same consent guard as the /status endpoint — publishing a
 * non-consented review is rejected on every path.
 */
class AdminFeedbackUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route group enforces auth:sanctum + cockpit
    }

    public function rules(): array
    {
        return [
            'order_id' => [
                'nullable', 'integer', 'exists:orders,id',
                Rule::unique('feedback', 'order_id')
                    ->whereNull('deleted_at')
                    ->ignore($this->route('feedback')),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'project_label' => ['nullable', 'string', 'max:255'],

            'overall' => ['nullable', 'integer', 'between:1,5'],
            'rating_design' => ['nullable', 'integer', 'between:1,5'],
            'rating_communication' => ['nullable', 'integer', 'between:1,5'],
            'rating_delivery' => ['nullable', 'integer', 'between:1,5'],
            'rating_value' => ['nullable', 'integer', 'between:1,5'],
            'nps' => ['nullable', 'integer', 'between:0,10'],
            'praise' => ['nullable', 'string', 'max:2000'],
            'improve' => ['nullable', 'string', 'max:2000'],

            'publish_consent' => ['sometimes', 'boolean'],
            'attribution_name' => ['nullable', 'string', 'max:255'],
            'attribution_role' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:pending,approved,published,archived'],
            'featured' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
