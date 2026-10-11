<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmailLogResource;
use App\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * GET /api/v1/admin/emails — the email history behind each record's "Emails"
 * panel (spec §5.9). Filter by a record (`related_type` + `related_id`, the
 * EmailLog::RELATED aliases) or by an address (`email` — a client page lists
 * everything ever sent to them). Newest first, 25 a page.
 */
class EmailLogsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'related_type' => ['required_without:email', 'nullable', Rule::in(array_keys(EmailLog::RELATED))],
            'related_id' => ['required_with:related_type', 'nullable', 'integer'],
            'email' => ['required_without:related_type', 'nullable', 'email', 'max:255'],
        ]);

        $query = EmailLog::with('triggeredBy:id,name')
            ->latest('created_at')
            ->latest('id');

        if (filled($data['related_type'] ?? null)) {
            $query->where('related_type', $data['related_type'])->where('related_id', $data['related_id']);
        } else {
            $query->where('to_email', $data['email']);
        }

        return EmailLogResource::collection($query->paginate(25));
    }
}
