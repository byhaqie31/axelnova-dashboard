<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\User;
use App\Services\Auth\AccessLinks;
use App\Services\Auth\InvalidAccessLink;
use App\Services\Mail\OwnerHeadsUps;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET/POST /api/v1/auth/access-links/{token} — behind the team, partner and
 * admin "set password" pages (Phase 2 spec §3.2). GET says whose link it is
 * and how long the secret must be; POST sets it, once. Unknown, expired and
 * used links all get the same 410.
 */
class AccessLinkController extends Controller
{
    public function __construct(private readonly AccessLinks $links) {}

    public function show(string $token): JsonResponse
    {
        $link = $this->links->inspect($token);
        if ($link === null) {
            return $this->gone();
        }

        $account = $link->account;
        $name = $account instanceof User ? $account->name : $account->displayName();

        return response()->json([
            'purpose' => $link->purpose,
            'surface' => $link->surface,
            'name' => $name !== '' ? $name : null,
            'email' => $account->email,
            'min_length' => $this->links->minimumLength($link->surface),
        ]);
    }

    public function store(Request $request, string $token, OwnerHeadsUps $headsUps): JsonResponse
    {
        $link = $this->links->inspect($token);
        if ($link === null) {
            return $this->gone();
        }

        $request->validate([
            'secret' => ['required', 'string', 'min:'.$this->links->minimumLength($link->surface), 'max:200', 'confirmed'],
        ]);

        try {
            $account = $this->links->redeem($token, (string) $request->input('secret'));
        } catch (InvalidAccessLink) {
            return $this->gone();
        }

        // A reset (not a first set-up) tells the owner who just changed credentials.
        if ($link->purpose === AccessToken::RESET) {
            $headsUps->credentialsChanged($account, $link->surface);
        }

        return response()->json([
            'message' => $link->surface === 'partner' ? 'Your passcode is set.' : 'Your password is set.',
            'login_url' => $this->links->loginUrl($link->surface),
        ]);
    }

    private function gone(): JsonResponse
    {
        return response()->json(['message' => (new InvalidAccessLink)->getMessage()], 410);
    }
}
