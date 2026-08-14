<?php

namespace App\Models;

use App\Observers\ClientObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([ClientObserver::class])]
class Client extends Model
{
    use HasFactory, SoftDeletes;

    /** Contact fields quotations denormalise into their own snapshot columns. */
    public const CONTACT_FIELDS = ['name', 'email', 'phone', 'company'];

    protected $fillable = ['name', 'email', 'phone', 'company', 'notes', 'tags'];

    protected function casts(): array
    {
        return ['tags' => 'array'];
    }

    /**
     * Upsert a client by email for a TRUSTED writer (admin builder, MCP
     * connector). Per-FIELD last-write-wins: every supplied, non-empty field
     * updates the existing row; an absent, null, or empty-string field preserves
     * what's already stored. So a sparse payload can never wipe a phone number
     * that was filled in by hand, and an explicit new company is never silently
     * dropped (the old firstOrCreate behaviour). Untrusted public intake (the
     * funnel, inquiries) deliberately keeps firstOrCreate — anonymous input must
     * not overwrite a curated record.
     *
     * @param  array{name?: ?string, email: string, phone?: ?string, company?: ?string}  $fields
     */
    public static function upsertContact(array $fields): Client
    {
        $client = static::firstOrNew(['email' => $fields['email']]);

        foreach (['name', 'phone', 'company'] as $field) {
            $value = $fields[$field] ?? null;
            if ($value !== null && trim((string) $value) !== '') {
                $client->{$field} = $value;
            }
        }

        // A brand-new row must still satisfy the NOT NULL name column even if a
        // caller ever passes a blank name (both current writers validate it).
        if (! $client->exists && blank($client->name)) {
            $client->name = 'Client';
        }

        $client->save();

        return $client;
    }

    /**
     * Resolve the target client for a re-link: an existing row by id, or an
     * upsert-by-email for the "create new" path. Email is the natural key, so a
     * create-new whose email already exists LINKS to that row (never a duplicate)
     * without overwriting its details — mirrors the funnel's firstOrCreate.
     *
     * @param  array{client_id?: int|null, client?: array{name: string, email: string, phone?: ?string, company?: ?string}}  $data
     * @return array{0: Client, 1: bool} [client, linkedToExisting]
     */
    public static function resolveForRelink(array $data): array
    {
        if (! empty($data['client_id'])) {
            return [static::findOrFail($data['client_id']), false];
        }

        $fields = $data['client'];
        $client = static::firstOrCreate(
            ['email' => $fields['email']],
            [
                'name' => $fields['name'],
                'phone' => $fields['phone'] ?? null,
                'company' => $fields['company'] ?? null,
            ],
        );

        // wasRecentlyCreated=false on the create-new path means the email matched
        // an existing client — surfaced so the UI can say "linked to existing X".
        return [$client, ! $client->wasRecentlyCreated];
    }

    /**
     * Refresh the denormalised contact snapshot on every quotation pointing at
     * this client, so quotation cards/PDFs never drift from the canonical record.
     * Bulk update — one query, no per-row events needed.
     */
    public function syncQuotationSnapshots(): void
    {
        $this->quotations()->update([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
        ]);
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
