<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceScopeFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // field_key is unique within its category. Scope the unique check to the
        // (new or unchanged) category and ignore self on update.
        $categoryId = $this->input('service_category_id');
        $existingId = $this->route('serviceScopeField')?->id;

        $keyRule = Rule::unique('service_scope_fields', 'field_key')
            ->where('service_category_id', $categoryId);
        if ($existingId) {
            $keyRule->ignore($existingId);
        }

        return [
            'service_category_id' => ['required', 'exists:service_categories,id'],
            'field_key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', $keyRule],
            'label' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['slider', 'select', 'toggle'])],
            'applies_to' => ['nullable', 'array'],
            'applies_to.*' => ['string', 'max:80'],
            'sort_order' => ['integer', 'min:0'],
            'active' => ['boolean'],

            'config' => ['required', 'array'],

            // slider
            'config.min' => ['required_if:type,slider', 'integer'],
            'config.max' => ['required_if:type,slider', 'integer', 'gt:config.min'],
            'config.default' => ['nullable'],
            'config.unit' => ['nullable', 'string', 'max:40'],
            'config.free_threshold' => ['required_if:type,slider', 'integer', 'min:0'],
            'config.price_per_unit' => ['required_if:type,slider', 'numeric', 'min:0'],

            // toggle
            'config.amount' => ['required_if:type,toggle', 'numeric', 'min:0'],

            // select
            'config.options' => ['required_if:type,select', 'array', 'min:1'],
            'config.options.*.value' => ['required_with:config.options', 'string', 'max:60'],
            'config.options.*.label' => ['required_with:config.options', 'string', 'max:100'],
            'config.options.*.amount' => ['required_with:config.options', 'numeric', 'min:0'],
        ];
    }
}
