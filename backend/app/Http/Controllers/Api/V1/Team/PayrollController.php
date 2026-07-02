<?php

namespace App\Http\Controllers\Api\V1\Team;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollEntryResource;
use App\Models\PayrollEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Own payslips for every internal role (the /team "Payslips" page). Scoped hard
 * to the token's own user id — partner and marketer alike read only their rows
 * here; the founder-only roll-up lives in Admin\PayrollController. Read-only:
 * entries are recorded by the founder in the cockpit.
 */
class PayrollController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $entries = PayrollEntry::where('user_id', $request->user()->id)
            ->latest()
            ->latest('id')
            ->paginate(20);

        return PayrollEntryResource::collection($entries);
    }
}
