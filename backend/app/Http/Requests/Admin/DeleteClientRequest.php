<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Delete a client, optionally naming the replacement its quotations/orders/
 * payments move to — the same shape as ClientLinkRequest (an existing
 * `client_id`, or a `client{}` for "create new"), but optional: a client with
 * no ties needs no replacement. If both arrive, client_id wins.
 */
class DeleteClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'client' => ['nullable', 'array'],
            'client.name' => ['required_with:client', 'string', 'min:2', 'max:150'],
            'client.email' => ['required_with:client', 'email:rfc', 'max:200'],
            'client.phone' => ['nullable', 'string', 'max:30'],
            'client.company' => ['nullable', 'string', 'max:200'],
        ];
    }

    /** The replacement input for Client::resolveForRelink, or null when none was given. */
    public function replacement(): ?array
    {
        $data = $this->validated();

        if (! empty($data['client_id'])) {
            return ['client_id' => $data['client_id']];
        }

        return ! empty($data['client']) ? ['client' => $data['client']] : null;
    }
}
