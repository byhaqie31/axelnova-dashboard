<?php

namespace App\Http\Requests\Connector;

use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the MCP connector's `list_quotations` query. Every filter is optional;
 * with none set the endpoint returns the newest non-deleted quotations. Unknown
 * status values are rejected with an instructive message (the valid set), matching
 * the connector's self-correcting error convention.
 */
class ListQuotationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Any of the lifecycle statuses; omit for all non-deleted rows.
            'status' => ['nullable', 'array'],
            'status.*' => ['string', Rule::in(Quotation::STATUSES)],

            // Free-text match on name / email / reference_code (admin search parity).
            'q' => ['nullable', 'string', 'max:200'],

            // Created (submitted) date range, inclusive, ISO dates (YYYY-MM-DD).
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],

            // Pagination — 10 per page by default; the controller caps it at 25.
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.*.in' => 'Unknown status. Valid statuses: '.implode(', ', Quotation::STATUSES).'.',
        ];
    }
}
