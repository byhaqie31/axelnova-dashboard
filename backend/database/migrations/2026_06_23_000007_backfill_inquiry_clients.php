<?php

use App\Models\Client;
use App\Models\Inquiry;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Backfill the customer spine: every existing inquiry gets a client, matched
     * by email (created where missing). One-way — down() is a no-op since we can't
     * know which clients pre-existed.
     */
    public function up(): void
    {
        Inquiry::query()
            ->whereNull('client_id')
            ->whereNotNull('email')
            ->get()
            ->each(function (Inquiry $inquiry) {
                $client = Client::firstOrCreate(
                    ['email' => $inquiry->email],
                    [
                        'name' => $inquiry->name,
                        'phone' => $inquiry->phone,
                        'company' => $inquiry->company,
                    ],
                );

                $inquiry->update(['client_id' => $client->id]);
            });
    }

    public function down(): void
    {
        // Irreversible — the link is dropped with the column in the companion migration.
    }
};
