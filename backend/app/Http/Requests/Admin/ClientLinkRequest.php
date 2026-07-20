<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Re-link an order/quotation to the correct client. The body is EITHER an
 * existing `client_id`, OR a `client{}` object for the "create new" path
 * (upserted by email — see Client::resolveForRelink). Exactly one is required;
 * if both arrive, client_id wins.
 */
class ClientLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required_without:client', 'nullable', 'integer', 'exists:clients,id'],
            'client' => ['required_without:client_id', 'nullable', 'array'],
            'client.name' => ['required_with:client', 'string', 'min:2', 'max:150'],
            'client.email' => ['required_with:client', 'email:rfc', 'max:200'],
            'client.phone' => ['nullable', 'string', 'max:30'],
            'client.company' => ['nullable', 'string', 'max:200'],
        ];
    }
}
