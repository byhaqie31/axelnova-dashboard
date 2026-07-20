<?php

namespace App\Observers;

use App\Models\Client;

class ClientObserver
{
    /**
     * Keep quotation contact snapshots in step with the canonical client. Orders
     * read contact through the client live, but QuotationResource serves the
     * quotation's OWN denormalised columns — so a client edit would leave those
     * stale until the next builder save. Re-sync whenever a contact field changes.
     */
    public function updated(Client $client): void
    {
        if ($client->wasChanged(Client::CONTACT_FIELDS)) {
            $client->syncQuotationSnapshots();
        }
    }
}
