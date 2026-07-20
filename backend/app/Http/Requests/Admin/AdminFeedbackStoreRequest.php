<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin create — three modes. `request` asks an order's client for a review
 * via the token link; `general` mints an order-less link for anyone (client,
 * prospect, partner — email optional, sent only when present); `log` records
 * feedback the founder already received offline, scores entered directly.
 */
class AdminFeedbackStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route group enforces auth:sanctum + cockpit
    }

    public function rules(): array
    {
        $isLog = $this->input('mode') === 'log';

        return [
            'mode' => ['required', 'in:request,general,log'],
            // request/general only: false = mint the link without emailing it,
            // so the admin can copy the URL and share it however they like.
            'send_email' => ['boolean'],
            // Anchors a client request; general mode is order-less by definition
            // and log mode may reference one.
            'order_id' => [
                Rule::requiredIf(fn () => $this->input('mode') === 'request'),
                'nullable', 'integer', 'exists:orders,id',
                // One feedback per order, NULLs exempt (soft-deleted rows keep the slot
                // free via whereNull deleted_at — the unique index still guards races).
                Rule::unique('feedback', 'order_id')->whereNull('deleted_at'),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            // Always optional — the link is sent only when an address exists.
            'email' => ['nullable', 'email', 'max:255'],
            'project_label' => ['nullable', 'string', 'max:255'],

            // Scores only make sense in log mode (request mode waits for the client).
            'overall' => [Rule::requiredIf(fn () => $isLog), 'nullable', 'integer', 'between:1,5'],
            'rating_design' => ['nullable', 'integer', 'between:1,5'],
            'rating_communication' => ['nullable', 'integer', 'between:1,5'],
            'rating_delivery' => ['nullable', 'integer', 'between:1,5'],
            'rating_value' => ['nullable', 'integer', 'between:1,5'],
            'nps' => ['nullable', 'integer', 'between:0,10'],
            'praise' => ['nullable', 'string', 'max:2000'],
            'improve' => ['nullable', 'string', 'max:2000'],

            'publish_consent' => ['boolean'],
            'attribution_name' => ['nullable', 'string', 'max:255'],
            'attribution_role' => ['nullable', 'string', 'max:255'],
            'featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
