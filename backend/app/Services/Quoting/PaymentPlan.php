<?php

namespace App\Services\Quoting;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The deposit + payment-plan view of a quotation, derived from its stored
 * `document` and its agreed total. The SINGLE place every reader (PDF mapper,
 * admin resource, connector view, order creation) gets deposit figures from.
 *
 * Inputs live on `document` beside the legacy `deposit_pct` (no new columns,
 * nothing backfilled — a row without the new keys behaves exactly as before):
 *
 *   deposit_pct             int 0–100      legacy percentage (fallback 50)
 *   deposit_amount_myr      float|null     fixed deposit in ringgit — WINS over the pct
 *   payment_plan            lump_sum (default) | instalment | partner
 *   instalment_months       int            instalment / partner (partner defaults to 24)
 *   instalment_amount_myr   float          the monthly figure
 *   billing_day             int 1–28       default 20
 *   first_instalment_date   Y-m-d|null     null → the first billing day after the anchor
 *   includes_care_plan      bool
 *   phases                  list<{label, pct}>|null  lump sum only: a DYNAMIC by-phase split
 *                           (40/40/20, 50/50, 20×5 …) — ≥ 2 phases, pcts summing to 100.
 *                           Phase 1 is the up-front payment; each phase is round(total ×
 *                           pct / 100) to the ringgit and the LAST phase takes the rounding
 *                           remainder, so the phases always sum to the total exactly.
 *   care_plan_monthly_myr   float|null     a SEPARATE Care Plan subscription (RM / month),
 *                           billed month by month on its own invoices — distinct from
 *                           includes_care_plan (care folded into the instalment figure)
 *
 * Rounding rule (documented once, here): a percentage deposit is `round(total ×
 * pct / 100)` to the NEAREST RINGGIT, halves away from zero (PHP's default
 * round()), e.g. 19% of RM 14,340 = RM 2,724.60 → RM 2,725. A fixed amount is
 * taken as given. The effective percentage of a fixed amount is derived for
 * display only (one decimal place, e.g. "18.8%") and is never written back —
 * storing a rounded pct would drift from the agreed amount.
 */
final class PaymentPlan
{
    public const LUMP_SUM = 'lump_sum';

    public const INSTALMENT = 'instalment';

    public const PARTNER = 'partner';

    public const PLANS = [self::LUMP_SUM, self::INSTALMENT, self::PARTNER];

    public const DEFAULT_DEPOSIT_PCT = 50;

    public const DEFAULT_BILLING_DAY = 20;

    public const PARTNER_DEFAULT_MONTHS = 24;

    /** The document keys this object reads — what a writer may store. */
    public const DOCUMENT_KEYS = [
        'deposit_pct', 'deposit_amount_myr', 'payment_plan', 'instalment_months',
        'instalment_amount_myr', 'billing_day', 'first_instalment_date', 'includes_care_plan',
        'phases', 'care_plan_monthly_myr',
    ];

    /** A phased lump sum has between MIN_PHASES and MAX_PHASES phases. */
    public const MIN_PHASES = 2;

    public const MAX_PHASES = 10;

    private const STANDARD_TAIL = '; balance due on delivery before handover.';

    private function __construct(
        private readonly string $plan,
        private readonly float $total,
        private readonly int $depositPct,
        private readonly ?float $fixedDeposit,
        private readonly int $months,
        private readonly float $instalmentAmount,
        private readonly int $billingDay,
        private readonly ?CarbonImmutable $firstDate,
        private readonly bool $includesCarePlan,
        private readonly bool $explicit,
        private readonly CarbonImmutable $anchor,
        /** @var list<array{label: string, pct: float}> */
        private readonly array $phases = [],
        private readonly ?float $carePlanMonthly = null,
    ) {}

    /**
     * @param  array<string, mixed>|null  $document  The quotation's `document` (or any array carrying the keys above).
     * @param  float  $total  The agreed quotation total (Quotation::finalAmount()).
     * @param  CarbonInterface|null  $anchor  Date the schedule counts from when no first date is stored (the issue date).
     */
    public static function fromDocument(?array $document, float $total, ?CarbonInterface $anchor = null): self
    {
        $doc = is_array($document) ? $document : [];

        $plan = (string) ($doc['payment_plan'] ?? self::LUMP_SUM);
        if (! in_array($plan, self::PLANS, true)) {
            $plan = self::LUMP_SUM;
        }

        $pct = (int) ($doc['deposit_pct'] ?? 0);
        $fixed = self::nullableFloat($doc['deposit_amount_myr'] ?? null);

        $months = (int) ($doc['instalment_months'] ?? 0);
        if ($months <= 0) {
            $months = $plan === self::PARTNER ? self::PARTNER_DEFAULT_MONTHS : 0;
        }

        $billingDay = (int) ($doc['billing_day'] ?? self::DEFAULT_BILLING_DAY);
        $billingDay = max(1, min(28, $billingDay));

        $first = null;
        if (! empty($doc['first_instalment_date'])) {
            try {
                $first = CarbonImmutable::parse((string) $doc['first_instalment_date'])->startOfDay();
            } catch (\Throwable) {
                $first = null;
            }
        }

        $explicit = array_key_exists('payment_plan', $doc) || $fixed !== null;
        $phases = $plan === self::LUMP_SUM ? self::normalisePhases($doc['phases'] ?? null) : [];

        return new self(
            plan: $plan,
            total: round(max($total, 0), 2),
            depositPct: $pct > 0 ? $pct : self::DEFAULT_DEPOSIT_PCT,
            fixedDeposit: $fixed,
            months: $months,
            instalmentAmount: round(max((float) ($doc['instalment_amount_myr'] ?? 0), 0), 2),
            billingDay: $billingDay,
            firstDate: $first,
            includesCarePlan: filter_var($doc['includes_care_plan'] ?? false, FILTER_VALIDATE_BOOL),
            explicit: $explicit || $phases !== [],
            anchor: ($anchor ? CarbonImmutable::instance($anchor) : CarbonImmutable::now())->startOfDay(),
            phases: $phases,
            carePlanMonthly: ($care = self::nullableFloat($doc['care_plan_monthly_myr'] ?? null)) !== null && $care > 0 ? $care : null,
        );
    }

    /**
     * A usable phase list, or [] (→ the classic deposit + balance lump sum):
     * MIN_PHASES…MAX_PHASES rows, each pct > 0, summing to 100 (±0.01). Labels
     * are the founder's content and print as authored; a blank one falls back
     * to "Phase n".
     *
     * @return list<array{label: string, pct: float}>
     */
    public static function normalisePhases(mixed $raw): array
    {
        if (! is_array($raw) || count($raw) < self::MIN_PHASES || count($raw) > self::MAX_PHASES) {
            return [];
        }

        $phases = [];
        foreach (array_values($raw) as $i => $row) {
            $pct = is_array($row) && is_numeric($row['pct'] ?? null) ? round((float) $row['pct'], 2) : 0.0;
            if ($pct <= 0) {
                return [];
            }
            $label = is_array($row) ? trim((string) ($row['label'] ?? '')) : '';
            $phases[] = ['label' => $label !== '' ? $label : 'Phase '.($i + 1), 'pct' => $pct];
        }

        return abs(array_sum(array_column($phases, 'pct')) - 100) < 0.01 ? $phases : [];
    }

    /**
     * Pick + coerce the plan keys out of an arbitrary input array (a connector
     * `detailed` block, a request body) into the shape a writer stores on the
     * document. Absent keys stay absent so a legacy-shaped write stays legacy.
     *
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    public static function inputsFrom(array $source): array
    {
        $out = [];
        if (array_key_exists('deposit_pct', $source) && $source['deposit_pct'] !== null && $source['deposit_pct'] !== '') {
            $out['deposit_pct'] = (int) $source['deposit_pct'];
        }
        if (($fixed = self::nullableFloat($source['deposit_amount_myr'] ?? null)) !== null) {
            $out['deposit_amount_myr'] = $fixed;
        }
        if (! empty($source['payment_plan']) && in_array($source['payment_plan'], self::PLANS, true)) {
            $out['payment_plan'] = (string) $source['payment_plan'];
        }
        if (! empty($source['instalment_months'])) {
            $out['instalment_months'] = (int) $source['instalment_months'];
        }
        if (($monthly = self::nullableFloat($source['instalment_amount_myr'] ?? null)) !== null) {
            $out['instalment_amount_myr'] = $monthly;
        }
        if (! empty($source['billing_day'])) {
            $out['billing_day'] = (int) $source['billing_day'];
        }
        if (! empty($source['first_instalment_date'])) {
            $out['first_instalment_date'] = (string) $source['first_instalment_date'];
        }
        if (array_key_exists('includes_care_plan', $source) && $source['includes_care_plan'] !== null) {
            $out['includes_care_plan'] = filter_var($source['includes_care_plan'], FILTER_VALIDATE_BOOL);
        }
        if (($phases = self::normalisePhases($source['phases'] ?? null)) !== []) {
            $out['phases'] = $phases;
        }
        if (($care = self::nullableFloat($source['care_plan_monthly_myr'] ?? null)) !== null && $care > 0) {
            $out['care_plan_monthly_myr'] = $care;
        }

        return $out;
    }

    // ── Plan ─────────────────────────────────────────────────────────────────

    public function plan(): string
    {
        return $this->plan;
    }

    public function total(): float
    {
        return $this->total;
    }

    /** True once the document carries the new keys (a fixed amount or an explicit plan). */
    public function isExplicit(): bool
    {
        return $this->explicit;
    }

    /** Instalment / partner — a plan with a monthly schedule. */
    public function isScheduled(): bool
    {
        return $this->plan !== self::LUMP_SUM;
    }

    // ── Deposit ──────────────────────────────────────────────────────────────

    /** The stored percentage (legacy readers; fallback 50). Not what renders when a fixed amount is set. */
    public function depositPct(): int
    {
        return $this->depositPct;
    }

    public function isFixedDeposit(): bool
    {
        return $this->fixedDeposit !== null;
    }

    /** Phase 1 of a phased lump sum; else the fixed amount; else pct × total to the ringgit. Never above the total. */
    public function depositAmount(): float
    {
        if ($this->phases !== []) {
            return $this->phaseAmounts()[0];
        }
        if ($this->fixedDeposit === null) {
            return self::pctDepositAmount($this->total, $this->depositPct);
        }

        return (float) min(max($this->fixedDeposit, 0), $this->total);
    }

    /**
     * THE percentage-deposit rule, shared by the quotation (depositAmount) and the
     * order (Order::deposit_due_myr) so the deposit invoice never differs from the
     * quoted figure: total × pct / 100 rounded to the NEAREST RINGGIT (halves away
     * from zero), clamped to 0…total. 19% of RM 14,340 = RM 2,724.60 → RM 2,725.
     */
    public static function pctDepositAmount(float $total, int $pct): float
    {
        $total = max($total, 0);

        return (float) min(max(round($total * $pct / 100), 0), $total);
    }

    public function balance(): float
    {
        return round($this->total - $this->depositAmount(), 2);
    }

    /** Derived from the amount — unrounded. Display via depositPctLabel(). */
    public function effectiveDepositPct(): float
    {
        return $this->total > 0 ? $this->depositAmount() / $this->total * 100 : 0.0;
    }

    /** "18.8%" — one decimal place, trimmed when integral ("50%"). */
    public function depositPctLabel(): string
    {
        $pct = round($this->effectiveDepositPct(), 1);

        return (floor($pct) === $pct ? number_format($pct, 0) : number_format($pct, 1)).'%';
    }

    /** "RM 2,700 · 18.8%" — the read-only admin display. */
    public function depositLabel(): string
    {
        return 'RM '.self::fmt($this->depositAmount()).' · '.$this->depositPctLabel();
    }

    // ── Instalments ──────────────────────────────────────────────────────────

    public function months(): int
    {
        return $this->months;
    }

    public function instalmentAmount(): float
    {
        return $this->instalmentAmount;
    }

    public function billingDay(): int
    {
        return $this->billingDay;
    }

    public function includesCarePlan(): bool
    {
        return $this->includesCarePlan;
    }

    /** Deposit + months × monthly for a scheduled plan; the quotation total otherwise. */
    public function planTotal(): float
    {
        if (! $this->isScheduled()) {
            return $this->total;
        }

        return round($this->depositAmount() + $this->months * $this->instalmentAmount, 2);
    }

    /** total − planTotal: positive = the plan collects less than quoted, negative = more. */
    public function variance(): float
    {
        return round($this->total - $this->planTotal(), 2);
    }

    public function reconciles(): bool
    {
        return abs($this->variance()) < 0.005;
    }

    /**
     * The first instalment date: the stored one, else the first billing day
     * strictly after the anchor (issue) date.
     */
    public function firstInstalmentDate(): ?CarbonImmutable
    {
        if (! $this->isScheduled() || $this->months <= 0) {
            return null;
        }
        if ($this->firstDate) {
            return $this->firstDate;
        }

        $base = $this->anchor->day < $this->billingDay
            ? $this->anchor
            : $this->anchor->startOfMonth()->addMonthNoOverflow();

        return $base->setDay(min($this->billingDay, $base->daysInMonth));
    }

    public function lastInstalmentDate(): ?CarbonImmutable
    {
        $first = $this->firstInstalmentDate();

        return $first ? self::scheduleDate($first, $this->months - 1, $this->billingDay) : null;
    }

    /**
     * Instalment i (0-based) counted from the first date: i = 0 is the first
     * date as given; every later one falls on the billing day of the i-th month
     * after it, CLAMPED to that month's last day (31 → 28/29 Feb, 30 Apr …).
     */
    public static function scheduleDate(CarbonImmutable $first, int $i, int $billingDay): CarbonImmutable
    {
        if ($i <= 0) {
            return $first;
        }

        $month = $first->startOfMonth()->addMonthsNoOverflow($i);

        return $month->setDay(min(max($billingDay, 1), $month->daysInMonth));
    }

    /**
     * The plan inputs an ORDER keeps once the quotation is accepted — null for a
     * lump sum. The first date is RESOLVED (a blank one would otherwise drift with
     * the order's own dates), so the order's schedule is fixed at acceptance.
     *
     * @return array{payment_plan: string, instalment_months: int, instalment_amount_myr: float, billing_day: int, first_instalment_date: ?string, includes_care_plan: bool}|null
     */
    public function orderSnapshot(): ?array
    {
        if ($this->isPhased() || (! $this->isScheduled() && $this->carePlanMonthly !== null)) {
            return array_filter([
                'payment_plan' => self::LUMP_SUM,
                'phases' => $this->phases ?: null,
                'care_plan_monthly_myr' => $this->carePlanMonthly,
            ], fn ($v) => $v !== null);
        }
        if (! $this->isScheduled() || $this->months <= 0) {
            return null;
        }

        return array_filter([
            'payment_plan' => $this->plan,
            'instalment_months' => $this->months,
            'instalment_amount_myr' => $this->instalmentAmount,
            'billing_day' => $this->billingDay,
            'first_instalment_date' => $this->firstInstalmentDate()?->toDateString(),
            'includes_care_plan' => $this->includesCarePlan,
            'care_plan_monthly_myr' => $this->carePlanMonthly,
        ], fn ($v) => $v !== null);
    }

    /** "Instalment 3 of 12" / "Monthly fee 3 of 24" — the invoice label for instalment n. */
    public function instalmentLabel(int $n): string
    {
        return ($this->plan === self::PARTNER ? 'Monthly fee' : 'Instalment')." {$n} of {$this->months}";
    }

    /** "Deposit" / "Setup fee" — what the up-front payment is called on this plan. */
    public function upfrontLabel(): string
    {
        return $this->plan === self::PARTNER ? 'Setup fee' : 'Deposit';
    }

    /** The due date of instalment n (1-based), or null outside the schedule. */
    public function instalmentDate(int $n): ?CarbonImmutable
    {
        $first = $this->firstInstalmentDate();
        if ($first === null || $n < 1 || $n > $this->months) {
            return null;
        }

        return self::scheduleDate($first, $n - 1, $this->billingDay);
    }

    /**
     * The derived instalment list (the deposit is not an instalment — it's due
     * on acceptance and shown separately).
     *
     * @return list<array{n: int, date: ?string, amount: float}>
     */
    public function schedule(): array
    {
        $first = $this->firstInstalmentDate();
        if ($first === null) {
            return [];
        }

        $rows = [];
        for ($i = 0; $i < $this->months; $i++) {
            $rows[] = [
                'n' => $i + 1,
                'date' => self::scheduleDate($first, $i, $this->billingDay)->toDateString(),
                'amount' => $this->instalmentAmount,
            ];
        }

        return $rows;
    }

    // ── Phases (dynamic lump sum) ────────────────────────────────────────────

    /** A lump sum split into founder-defined phases (40/40/20, 50/50, 20×5 …). */
    public function isPhased(): bool
    {
        return $this->phases !== [];
    }

    /** @return list<array{label: string, pct: float}> */
    public function phases(): array
    {
        return $this->phases;
    }

    /**
     * Each phase's ringgit amount: round(total × pct / 100) to the nearest
     * ringgit (the deposit rule), the LAST phase taking the remainder so the
     * phases sum to the total exactly. 40/40/20 of RM 10,800 → 4,320 / 4,320 / 2,160.
     *
     * @return list<float>
     */
    public function phaseAmounts(): array
    {
        $amounts = [];
        $running = 0.0;
        $last = count($this->phases) - 1;
        foreach ($this->phases as $i => $phase) {
            $amount = $i === $last
                ? round($this->total - $running, 2)
                : (float) round($this->total * $phase['pct'] / 100);
            $amounts[] = max($amount, 0.0);
            $running += $amount;
        }

        return $amounts;
    }

    /** "Phase 2 of 3 · At demo" — the invoice label for phase n (1-based). */
    public function phaseLabel(int $n): string
    {
        $count = count($this->phases);
        $label = $this->phases[$n - 1]['label'] ?? null;

        return "Phase {$n} of {$count}".($label !== null && $label !== "Phase {$n}" ? " · {$label}" : '');
    }

    /** "40%" — a phase's share, trimmed when integral. */
    public static function pctLabel(float $pct): string
    {
        return (floor($pct) === $pct ? number_format($pct, 0) : rtrim(rtrim(number_format($pct, 2), '0'), '.')).'%';
    }

    // ── Care Plan ────────────────────────────────────────────────────────────

    /** A separate monthly Care Plan subscription (RM / month), or null. */
    public function carePlanMonthly(): ?float
    {
        return $this->carePlanMonthly;
    }

    /** "included" (inside the monthly figure), "separate" (own subscription) or null (none). */
    public function careKind(): ?string
    {
        if ($this->isScheduled() && $this->months > 0 && $this->includesCarePlan) {
            return 'included';
        }

        return $this->carePlanMonthly !== null ? 'separate' : null;
    }

    // ── Netting ──────────────────────────────────────────────────────────────

    /**
     * The plan's payments in the order they fall due (up-front first) — the
     * schedule a revision's paid-to-date is netted against (ScheduleNetting).
     * Keys are stable ids: `deposit`, `balance`, `phase:n`, `instalment:n`.
     *
     * @return list<array{key: string, label: string, amount: float, date: ?string}>
     */
    public function scheduleItems(): array
    {
        if ($this->isPhased()) {
            return array_map(fn (array $phase, float $amount, int $i) => [
                'key' => 'phase:'.($i + 1),
                'label' => $this->phaseLabel($i + 1),
                'amount' => $amount,
                'date' => null,
            ], $this->phases, $this->phaseAmounts(), array_keys($this->phases));
        }

        $items = [['key' => 'deposit', 'label' => $this->upfrontLabel(), 'amount' => $this->depositAmount(), 'date' => null]];

        if (! $this->isScheduled()) {
            $items[] = ['key' => 'balance', 'label' => 'Balance on completion', 'amount' => $this->balance(), 'date' => null];
        } else {
            foreach ($this->schedule() as $row) {
                $items[] = [
                    'key' => 'instalment:'.$row['n'],
                    'label' => $this->instalmentLabel($row['n']),
                    'amount' => $row['amount'],
                    'date' => $row['date'],
                ];
            }
        }

        return array_values(array_filter($items, fn (array $item) => $item['amount'] > 0));
    }

    // ── Projections ──────────────────────────────────────────────────────────

    /**
     * The admin + connector projection (QuotationResource / connectorView).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'plan' => $this->plan,
            'total_myr' => $this->total,
            'deposit_pct' => $this->depositPct,
            'deposit_fixed' => $this->isFixedDeposit(),
            'deposit_amount_myr' => $this->depositAmount(),
            'effective_deposit_pct' => round($this->effectiveDepositPct(), 2),
            'deposit_pct_label' => $this->depositPctLabel(),
            'balance_myr' => $this->balance(),
            'instalment_months' => $this->isScheduled() ? $this->months : null,
            'instalment_amount_myr' => $this->isScheduled() ? $this->instalmentAmount : null,
            'billing_day' => $this->isScheduled() ? $this->billingDay : null,
            'first_instalment_date' => $this->firstInstalmentDate()?->toDateString(),
            'last_instalment_date' => $this->lastInstalmentDate()?->toDateString(),
            'includes_care_plan' => $this->includesCarePlan,
            'plan_total_myr' => $this->planTotal(),
            'variance_myr' => $this->variance(),
            'reconciles' => $this->reconciles(),
            'schedule' => $this->schedule(),
            'phases' => $this->phaseRows(),
            'care_plan_monthly_myr' => $this->carePlanMonthly,
        ];
    }

    /**
     * The phases with their amounts — what the admin, connector and PDF show.
     *
     * @return list<array{n: int, label: string, pct: float, pct_label: string, amount: float}>
     */
    public function phaseRows(): array
    {
        $amounts = $this->phaseAmounts();

        return array_map(fn (array $phase, int $i) => [
            'n' => $i + 1,
            'label' => $phase['label'],
            'pct' => $phase['pct'],
            'pct_label' => self::pctLabel($phase['pct']),
            'amount' => $amounts[$i],
        ], $this->phases, array_keys($this->phases));
    }

    /**
     * The deposit / balance (or deposit / monthly) cards for the PDF — DATA
     * ONLY: a `role` plus the figures the renderer needs to label the card from
     * its locale file (en / bm). No label, note or month name is baked here,
     * so the same payload prints in either language. Roles:
     *
     *   lump_deposit    { value, pctLabel }                 "Deposit (18.8%)"
     *   lump_balance    { value, accent }                   "Balance on completion"
     *   inst_deposit    { value }                           "Deposit on acceptance"
     *   inst_monthly    { value, accent, months, billingDay, firstDate, lastDate }
     *   partner_setup   { value }                           "Setup fee"
     *   partner_monthly { value, accent, months, billingDay, firstDate, lastDate }
     *
     * @return list<array<string, mixed>>
     */
    public function panels(): array
    {
        $deposit = $this->depositAmount();
        if ($this->total <= 0) {
            return [];
        }

        // A phased lump sum prints its phases as a table (documentBlock), not cards.
        if ($this->isPhased()) {
            return [];
        }

        if ($this->isScheduled()) {
            $partner = $this->plan === self::PARTNER;

            return [
                ['role' => $partner ? 'partner_setup' : 'inst_deposit', 'value' => $deposit],
                [
                    'role' => $partner ? 'partner_monthly' : 'inst_monthly',
                    'value' => $this->instalmentAmount,
                    'accent' => true,
                    'months' => $this->months,
                    'billingDay' => $this->billingDay,
                    'firstDate' => $this->firstInstalmentDate()?->toDateString(),
                    'lastDate' => $this->lastInstalmentDate()?->toDateString(),
                ],
            ];
        }

        if ($deposit <= 0) {
            return [];
        }

        return [
            ['role' => 'lump_deposit', 'value' => $deposit, 'pctLabel' => $this->depositPctLabel()],
            ['role' => 'lump_balance', 'value' => $this->balance(), 'accent' => true],
        ];
    }

    /**
     * The Payment plan section's data for the PDF (`DocumentData.paymentPlan`)
     * — figures, ISO dates and the dated schedule ONLY. Every heading, row
     * label, caption and month name comes from the renderer's locale file, so
     * nothing language-specific is derived here. Lump sum carries deposit +
     * balance and an empty schedule (it gets the section heading, no page
     * break); instalment / partner carry the monthly figures + schedule. Null
     * when there is nothing to pay.
     *
     * @return array<string, mixed>|null
     */
    public function documentBlock(): ?array
    {
        if ($this->total <= 0) {
            return null;
        }

        $scheduled = $this->isScheduled() && $this->months > 0;

        return [
            'plan' => $this->plan,
            'deposit' => $this->depositAmount(),
            'depositPctLabel' => $this->depositPctLabel(),
            'balance' => $this->balance(),
            'monthly' => $scheduled ? $this->instalmentAmount : 0.0,
            'months' => $scheduled ? $this->months : 0,
            'billingDay' => $this->billingDay,
            'firstDate' => $this->firstInstalmentDate()?->toDateString(),
            'lastDate' => $this->lastInstalmentDate()?->toDateString(),
            'includesCarePlan' => $scheduled && $this->includesCarePlan,
            'total' => $this->planTotal(),
            'schedule' => $this->schedule(),
            // Phased lump sum: one row per phase (label as authored, pct, amount).
            'phases' => array_map(fn (array $row) => [
                'n' => $row['n'], 'label' => $row['label'], 'pct' => $row['pct'], 'amount' => $row['amount'],
            ], $this->phaseRows()),
            // A separate Care Plan subscription (RM / month), printed under the plan.
            'carePlanMonthly' => $this->carePlanMonthly,
        ];
    }

    /** The boilerplate deposit bullet's opening clause, per plan. */
    public function depositTermOpening(): string
    {
        $deposit = 'RM '.self::fmt($this->depositAmount());

        return match (true) {
            $this->isPhased() => "{$deposit} (".self::pctLabel($this->phases[0]['pct']).') to commence',
            $this->plan === self::PARTNER => "{$deposit} setup fee to commence",
            $this->plan === self::INSTALMENT => "{$deposit} deposit to commence",
            $this->isFixedDeposit() => "{$deposit} deposit ({$this->depositPctLabel()}) to commence",
            default => "{$this->depositPct}% deposit to commence",
        };
    }

    /** The full standard deposit bullet for this plan (the first of DocumentMapper::defaultTerms). */
    public function depositTerm(): string
    {
        $opening = $this->depositTermOpening();

        if ($this->isPhased()) {
            $amounts = $this->phaseAmounts();
            $rest = [];
            foreach (array_slice($this->phases, 1, null, true) as $i => $phase) {
                $rest[] = 'RM '.self::fmt($amounts[$i]).' ('.self::pctLabel($phase['pct']).") {$phase['label']}";
            }

            return "{$opening}; then ".implode('; ', $rest).'.';
        }

        return match ($this->plan) {
            self::INSTALMENT => "{$opening}; balance of RM ".self::fmt($this->balance())
                ." payable in {$this->months} monthly instalments of RM ".self::fmt($this->instalmentAmount)
                .', billed on the '.self::ordinal($this->billingDay).' of each month.',
            self::PARTNER => "{$opening}; then RM ".self::fmt($this->instalmentAmount)
                ." monthly for {$this->months} months, billed on the ".self::ordinal($this->billingDay).' of each month.',
            default => $opening.self::STANDARD_TAIL,
        };
    }

    /**
     * Rewrite a terms list's boilerplate deposit bullet to this plan. A bullet
     * that is exactly the standard boilerplate is replaced whole (the "balance
     * on delivery" tail is wrong for a scheduled plan); one with a hand-edited
     * tail keeps it and only the opening figure is realigned. Hand-authored
     * bullets that don't start like the boilerplate pass through untouched.
     *
     * @param  list<mixed>  $terms
     * @return list<string>
     */
    public function alignTerms(array $terms): array
    {
        $opening = '(?:\d+(?:\.\d+)?%|RM ?[\d,]+(?:\.\d+)?) (?:deposit(?: \([\d.]+%\))?|setup fee|\([\d.]+%\)) to commence';
        $standard = '/^'.$opening.preg_quote(self::STANDARD_TAIL, '/').'$/';
        $instalment = '/^'.$opening.'; (?:balance of RM ?[\d,]+(?:\.\d+)? payable in \d+ monthly instalments|then RM ?[\d,]+(?:\.\d+)? monthly for \d+ months)\b.*$/';
        $phased = '/^'.$opening.'; then RM ?[\d,]+(?:\.\d+)? \([\d.]+%\) .*$/';

        return array_values(array_map(function ($term) use ($opening, $standard, $instalment, $phased): string {
            $term = (string) $term;
            if (preg_match($standard, $term) || preg_match($instalment, $term) || preg_match($phased, $term)) {
                return $this->depositTerm();
            }

            return (string) preg_replace('/^'.$opening.'\b/', $this->depositTermOpening(), $term);
        }, $terms));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * "2,700.00" / "970.50" — the house money format: comma thousands, always two
     * decimals. Mirrored by fmtRm() in frontend/app/composables/paymentPlan.ts
     * (the builder words the same terms bullet live) — KEEP IN SYNC.
     */
    public static function fmt(float $n): string
    {
        return number_format($n, 2);
    }

    private static function ordinal(int $n): string
    {
        $suffix = match (true) {
            $n % 100 >= 11 && $n % 100 <= 13 => 'th',
            $n % 10 === 1 => 'st',
            $n % 10 === 2 => 'nd',
            $n % 10 === 3 => 'rd',
            default => 'th',
        };

        return $n.$suffix;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }
}
