<?php
use App\Mail\PartnerPasscodeMail;
use App\Models\Referrer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$pass = 0; $fail = 0;
function check($l, $ok) { global $pass, $fail; $ok ? $pass++ : $fail++; echo ($ok ? "  PASS  " : "  FAIL  ").$l."\n"; }
function call($kernel, $m, $u, $body = [], $tok = null) {
    \Illuminate\Support\Facades\Auth::forgetGuards();
    $r = Request::create($u, $m, $body);
    $r->headers->set('Accept', 'application/json');
    if ($tok) $r->headers->set('Authorization', 'Bearer '.$tok);
    $res = $kernel->handle($r);
    return [$res->getStatusCode(), (string) $res->getContent()];
}

// makePasscode shape
$samples = collect(range(1, 20))->map(fn () => Referrer::makePasscode());
check('makePasscode() returns exactly 8 digits (incl. leading zeros)', $samples->every(fn ($p) => preg_match('/^\d{8}$/', $p) === 1));

Mail::fake();
DB::beginTransaction();
try {
    $u = substr(md5((string) mt_rand()), 0, 8);
    $r = Referrer::create(['code' => Referrer::makeUniqueCode(), 'name' => 'PC', 'email' => "pc.$u@e.com", 'relationship_tier' => 'cold', 'commission_pct' => 5, 'status' => 'pending']);
    $founder = User::where('role', 'founder')->first();
    $ft = $founder->createToken('v', ['cockpit'])->plainTextToken;

    call($kernel, 'POST', "/api/v1/admin/referral-partners/{$r->id}/approve", [], $ft);
    $captured = null;
    Mail::assertSent(PartnerPasscodeMail::class, function ($m) use (&$captured, $r) {
        if ($m->referrer->id === $r->id) $captured = $m->passcode;
        return $m->referrer->id === $r->id;
    });
    check('approve emails an 8-digit passcode', (bool) preg_match('/^\d{8}$/', (string) $captured));

    [$c, $b] = call($kernel, 'POST', '/api/v1/partner/login', ['email' => "pc.$u@e.com", 'passcode' => $captured]);
    check("login with the 8-digit passcode succeeds (200, got $c)", $c === 200 && str_contains($b, 'token'));

    [$cBad] = call($kernel, 'POST', '/api/v1/partner/login', ['email' => "pc.$u@e.com", 'passcode' => '00000000']);
    check("wrong passcode rejected (422, got $cBad)", $cBad === 422);
} catch (\Throwable $e) {
    echo '  EXCEPTION: '.$e->getMessage().' @ '.$e->getLine()."\n"; $fail++;
} finally {
    DB::rollBack();
}
echo "\n==== $pass passed, $fail failed ====\n";
