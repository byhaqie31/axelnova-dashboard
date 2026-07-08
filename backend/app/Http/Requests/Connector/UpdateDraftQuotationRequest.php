<?php

namespace App\Http\Requests\Connector;

/**
 * The MCP connector's update contract. Identical to the create contract
 * (DraftQuotationRequest — same client/package/modifier/add-on/line-item/detailed
 * validation, including the catalog-aware after() hook), plus the `reseed_document`
 * flag. The update is a full re-specification of the quotation's pricing basis; the
 * controller enforces the lifecycle gate (pre-send only) before it runs.
 */
class UpdateDraftQuotationRequest extends DraftQuotationRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            // When true, regenerate the document from the new scope even if the
            // current one was hand-edited by an admin. Default false: an edited
            // document is preserved and only the estimate is re-priced.
            'reseed_document' => ['nullable', 'boolean'],
        ]);
    }
}
