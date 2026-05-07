<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // Step 1 — backfill clients (deduplicated by email).
            $emails = DB::table('quotations')->pluck('email')->unique();

            foreach ($emails as $email) {
                $first = DB::table('quotations')->where('email', $email)->orderBy('id')->first();
                if (!$first) continue;

                DB::table('clients')->updateOrInsert(
                    ['email' => $email],
                    [
                        'name' => $first->name,
                        'phone' => $first->phone,
                        'company' => $first->company,
                        'created_at' => $first->submitted_at ?? now(),
                        'updated_at' => now(),
                    ],
                );
            }

            // Step 2 — wire client_id on every quotation.
            $clientByEmail = DB::table('clients')->pluck('id', 'email')->all();
            foreach ($clientByEmail as $email => $cid) {
                DB::table('quotations')->where('email', $email)->update(['client_id' => $cid]);
            }

            // Step 3 — promote 'package_key' from form_payload JSON into the new column for queryability.
            DB::table('quotations')
                ->whereNotNull('form_payload')
                ->whereNull('package_key')
                ->orderBy('id')
                ->each(function ($q) {
                    $payload = is_string($q->form_payload) ? json_decode($q->form_payload, true) : (array) $q->form_payload;
                    $key = $payload['package_key'] ?? null;
                    if ($key) {
                        DB::table('quotations')->where('id', $q->id)->update(['package_key' => $key]);
                    }
                });

            // Step 4 — create an Order for each converted quotation.
            $converted = DB::table('quotations')
                ->where('status', 'converted')
                ->orderBy('submitted_at')
                ->get();

            $countPerYear = [];
            foreach ($converted as $q) {
                $year = date('Y', strtotime($q->submitted_at));
                $countPerYear[$year] = ($countPerYear[$year] ?? 0) + 1;
                $orderNumber = sprintf('ORD-%s-%04d', $year, $countPerYear[$year]);

                DB::table('orders')->insert([
                    'order_number' => $orderNumber,
                    'quotation_id' => $q->id,
                    'client_id' => $q->client_id,
                    'value_min_myr' => $q->estimate_min_myr,
                    'value_max_myr' => $q->estimate_max_myr,
                    'status' => $q->project_status ?: 'pending',
                    'started_at' => $q->project_started_at,
                    'delivered_at' => $q->project_delivered_at,
                    'completed_at' => $q->project_completed_at,
                    'created_at' => $q->submitted_at ?? now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Reverse cleanly: blow away derived rows.
        DB::table('orders')->truncate();
        DB::table('quotations')->update(['client_id' => null, 'package_key' => null]);
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('clients')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
