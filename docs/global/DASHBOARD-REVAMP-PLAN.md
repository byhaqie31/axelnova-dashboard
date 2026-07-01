# DASHBOARD-REVAMP-PLAN

Revamp plan for `axelnova-dashboard` to support a small team, at two altitudes:
**CEO** decides what to build, buy, or defer. **CTO** specs how the kept parts are structured.
Built to hand to Claude Code phase by phase.

> Reconciled against `main` (billing subsystem present). All open decisions from planning are now settled — see each phase.
> Stack: Laravel 11 API (Sanctum, `CheckRole`, observers, `ReferenceCodeGenerator`, `SortOrder`), Nuxt 4 SPA (`adminNav.ts`, `admin.vue`, `portal.vue` stub). Repo conventions: pricing/config is data not deploys; commission stays derived never duplicated; AXN codes via `ReferenceCodeGenerator`; catalog ordering via `SortOrder`; cache invalidation via observers.

---

## Current repo state (what already exists on main)

**Live admin sections (12 flat nav items):** Dashboard · Clients · Inquiries · Quotations · Orders · Invoices · Payments · Referrals · Services · Projects · Investors · Analytics.

**Business subsystems already built:**
- Quote-to-cash: `inquiries` → `quotations` (+addons, admin builder) → `orders` (payment fields, schedule, documents).
- Billing: `invoices`, `receipts`, `payments`, `gateway_events` (+ models, `InvoicesController`, `PaymentsController`). Contracted → invoiced → collected, with gateway webhooks.
- Referrals: `referrals` (referrer inline, tiered commission, `linked_order_id`, commission email) — flat, not yet a partner programme.
- Catalog: `service_categories` / `service_packages` / `service_addons` / `service_scope_fields`, `projects`.
- Analytics: `page_views` / `entity_likes` collecting; `AnalyticsController` present.
- Public site has a `public/legal` pages area.

**Auth core — unchanged, the blocker:** `users.role` = `string` default `'admin'`; `CheckRole` = single-string equality; `/v1/admin/*` gated `role:admin`; Sanctum tokens, no expiry; one env-seeded admin.

**Not present (all BUILD items below are net-new):** role enum/tiers, gates, `activity_log`, `updated_by`, `referral_partners`, partner auth guard, referral onboarding form, attribution cookie + consent, `/team`, `/partners`, payroll/expense ledgers, grouped nav.

---

## 0. How to read this

Section 1 is the strategic frame. Section 3 is what Claude Code must **not** build. Sections 4–8 are the engineering spec. If a later request contradicts Section 3, flag it as scope creep.

Thesis: **build the moat, rent the plumbing, defer the unproven.** Axel Nova's value is brand, taste, delivery quality, retained subscription clients.

---

## 1. Strategic frame — build / buy / defer

| Component | Verdict | Why |
|---|---|---|
| RBAC: roles, tiers, gates | **BUILD** | Core to the app; no vendor replaces it |
| Attribution / activity log | **BUILD** | The asset — which channel/partner brings clients who *pay and retain* |
| Staff `/team` workspace | **BUILD (lean)** | Scoping existing features into a clean shell |
| Referral data model (normalize) | **BUILD** | Schema hygiene + feeds attribution |
| Referral partner portal + onboarding | **BUILD** | The recruiting hook: referrers watch earnings, which spreads the programme |
| Payroll + marketing ledgers (record-only) | **BUILD** | Founder decision — one in-system source of truth as the team grows |
| Referral **payout processing** (money-out) | **DEFER / manual** | Portal *shows* commission; funds-transfer stays manual via commission-email flow |
| Statutory payroll calc (EPF/SOCSO/PCB) | **BUY** | Compliance beast → Malaysian payroll SaaS, never this repo |
| Investor CRM (placeholder) | **DEFER** | Not team-enablement, not revenue this quarter |

**The asset:** with billing live, attribution measures **collected** revenue (via `payments`), not just contracted value — so you learn where clients who actually *pay* come from.

**Hires = leverage.** New engineer's first month → revenue/product (the BUILD rows), not payout/statutory plumbing (the DEFER/BUY rows).

---

## 2. Scope of this revamp

1. **Foundation** — roles, tiers, gates, provisioning (Phase 0)
2. **Attribution** — `updated_by` + `activity_log` + collected-revenue report (Phase 1)
3. **Referral data model** — `referral_partners`, inquiry attribution, `?ref` cookie + consent (Phase 2)
4. **Staff `/team` + grouped IA** — scoped workspace + regrouped sidebar (Phase 3)
5. **Referral partner portal** — isolated-auth `/partners`, onboarding flow, dashboard (Phase 4)
6. **In-system ledgers** — payroll + marketing spend, record-only (Phase 5)

## 3. What Claude Code must NOT build

- ❌ **Commission payout / funds-transfer engine.** Commission is derived + displayed; paid manually via the commission-email flow.
- ❌ **Statutory payroll calculation** (EPF/SOCSO/EIS/PCB). Ledger records amounts only.
- ❌ **In-app team chat / comms.** Slack / WhatsApp / Notion.
- ❌ A **separate app or repo** for staff or partners. `/team` and `/partners` are surfaces on shared infra.

---

## 4. Roles, tiers, permission matrix (locked)

Three surfaces, two internal tiers, plus an isolated partner surface.

- **Cockpit** (`/admin`, `users` guard): `founder`, `partner`
- **Workspace** (`/team`, `users` guard): `marketer`, `engineer`
- **Partners** (`/partners`, separate `referral_partners` guard): external referrers, no internal role

`partner` = trusted admin, **not** co-founder. Founder-only: user management, hard deletes, others' payroll, quotation `accept → order`. The **marketer manages the referral programme** end-to-end (approvals, passcode resets).

**Naming guardrail:** `partner` = the RBAC role only. The affiliate entity is `Referrer` (model) on table `referral_partners`; each referred company is a `Referral`. Never the bare `partner` string in code.

| Capability | founder | partner | marketer | engineer |
|---|:--:|:--:|:--:|:--:|
| Enters `/admin` | ✓ | ✓ | — | — |
| Enters `/team` | ✓ | ✓ | ✓ | ✓ |
| Clients / Quotations (view) | ✓ | ✓ | — | — |
| Quotation `accept → order` | ✓ | — | — | — |
| Orders | ✓ | ✓ | — | — |
| Invoices / Payments (billing) | ✓ | ✓ | — | — |
| Services / Projects (manage / read) | ✓ / ✓ | ✓ / ✓ | — / ✓ | — / ✓ |
| Inquiries (triage / respond) | ✓ | ✓ | ✓ | ✓ |
| Referral programme (manage, approve, reset passcode) | ✓ | ✓ | ✓ | — |
| Marketing spend (enter own / see all) | ✓ / ✓ | ✓ / ✓ | ✓ / — | — |
| Payroll (see all / see own) | ✓ / ✓ | — / ✓ | — / ✓ | — / ✓ |
| User management | ✓ | — | — | — |
| Hard deletes | ✓ | — | — | — |
| Activity log (view) | ✓ | ✓ | — | — |

---

## 5. Engineering phases

### Phase 0 — Foundation

**Goal:** four roles, two tiers, founder-only gates, safe provisioning.

Files: `migrations/xxxx_expand_user_roles.php` · `Models/User.php` · `Middleware/CheckRole.php` · `Providers/AppServiceProvider.php` · `routes/api.php` · `config/sanctum.php` · `Admin/UsersController.php`.

Role migration:
```php
Schema::table('users', fn (Blueprint $t) =>
    $t->enum('role', ['founder','partner','marketer','engineer'])->default('engineer')->change());
// Data step: set the existing admin row to role = 'founder'.
```

`User` helpers:
```php
public const COCKPIT_ROLES = ['founder', 'partner'];
public function tier(): string { return in_array($this->role, self::COCKPIT_ROLES, true) ? 'cockpit' : 'workspace'; }
public function isFounder(): bool { return $this->role === 'founder'; }
```

`CheckRole` rewrite (role list / tier keyword):
```php
public function handle(Request $request, Closure $next, string ...$roles): Response
{
    $user = $request->user();
    if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
    $allowed = collect($roles)->flatMap(fn ($r) => match ($r) {
        'cockpit'   => User::COCKPIT_ROLES,
        'workspace' => ['founder','partner','marketer','engineer'],
        default     => [$r],
    })->unique();
    return $allowed->contains($user->role) ? $next($request)
        : response()->json(['message' => 'Forbidden.'], 403);
}
```

Gates (`AppServiceProvider::boot`): `manage-users`, `hard-delete`, `accept-quote`, `view-all-payroll` — all `fn (User $u) => $u->isFounder()`. Call `Gate::authorize(...)` in the matching controller actions.

Route gating: `/v1/admin/*` → `role:cockpit`; `/v1/team/*` → `role:workspace`; `/v1/partner/*` → the separate `referral` guard.

Provisioning: `POST /v1/admin/users` (Gate `manage-users`), `PATCH .../{user}` (role change), `POST .../{user}/deactivate` (revoke tokens). Set `'expiration'` in `config/sanctum.php`.

**Acceptance:** a `marketer` → 403 on all `/v1/admin/*`; a `partner` → 403 on the four founder-only actions; founder can create/deactivate users.

---

### Phase 1 — Attribution (the asset)

**Goal:** every state change records who did it; revenue traces to source.

Files: `migrations/xxxx_add_updated_by_to_business_tables.php` · `migrations/xxxx_create_activity_log_table.php` · `Support/RecordsActivity.php` · `Admin/ActivityController.php` · `Admin/AnalyticsController.php` (edit) · `pages/admin/activity/index.vue`.

`updated_by` nullable FK → users on: `quotations`, `orders`, `inquiries`, `referrals`, `invoices`, `payments`, `service_categories`, `service_packages`, `projects`.

```php
Schema::create('activity_log', function (Blueprint $t) {
    $t->id();
    $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete(); // null = system/gateway
    $t->string('action', 60);
    $t->string('subject_type', 60);
    $t->unsignedBigInteger('subject_id');
    $t->json('changes')->nullable();
    $t->timestamp('created_at')->useCurrent();
    $t->index(['subject_type','subject_id']); $t->index('actor_id');
});
```

`RecordsActivity` trait: `logActivity($action, $changes)` from state-changing actions; set `updated_by = auth id`. Gateway-webhook payments log `actor_id = null`.

**Attribution query:** inquiries carry `referral_partner_id` (Phase 2) → quotations → orders → invoices → payments. `AnalyticsController` groups **collected `payments`** by inquiry `source` + `referrer`, contracted vs collected. Expose `GET /v1/admin/analytics/attribution`.

**Acceptance:** a quotation status change writes an `activity_log` row; a gateway payment logs `actor_id = null`; the attribution endpoint returns collected revenue by source.

---

### Phase 2 — Referral data model + attribution intake

**Goal:** normalize the referrer entity, attribute inquiries, capture the referral link via cookie. Data + intake only; the portal/onboarding UI is Phase 4.

Files: `migrations/xxxx_create_referral_partners_table.php` · `..._add_referral_partner_id_to_referrals.php` · `..._add_referral_partner_id_to_inquiries.php` · `..._backfill_referral_partners.php` · `Models/Referrer.php` · `Models/{Referral,Inquiry}.php` (edit) · `InquiryController.php` (edit) · frontend consent banner + legal page.

```php
Schema::create('referral_partners', function (Blueprint $t) {
    $t->id();
    $t->string('code', 32)->unique();               // link param value: ?ref=CODE
    $t->string('name', 150);
    $t->string('email', 200)->unique();
    $t->string('phone', 30)->nullable();
    $t->enum('relationship_tier', ['cold','warm','closed'])->default('cold');
    $t->unsignedTinyInteger('commission_pct');      // derived from tier (5/10/15)
    $t->boolean('agreed_terms')->default(false);
    $t->enum('status', ['pending','active','paused'])->default('pending'); // pending until approved
    $t->timestamps(); $t->softDeletes();
});
```
Then `referral_partner_id` nullable FK on `referrals` and `inquiries` (`nullOnDelete`).

**Attribution link + cookie:**
- Shareable link is a normal site URL with a query param: `https://axelnovaventures.com/?ref={code}` (param name `ref`; keep short and neutral, not `partner`).
- Landing with `?ref=` sets a first-party cookie `axn_ref` = the referrer code, **short-lived (60–90 days, matching the commission window)**, classified **functional**.
- `InquiryController@store` resolves the referrer: cookie `axn_ref` (or a `?ref` still on the URL) → set `inquiry.referral_partner_id` + `source = 'referral'`; absent → null + `source = 'web'`. **Null = public.**
- **First-touch wins:** if a cookie already holds a code, don't overwrite it with a later link. If the same company is referred twice, the first referrer keeps credit (configurable later).

**Cookie consent (do it now, since the cookie obliges it):**
- Lightweight consent banner; don't set non-essential cookies before consent.
- Cookie-policy page under `public/legal`.
- If consent declined, fall back to reading `?ref` server-side at inquiry submit (best-effort attribution without a persisted cookie).
- Note: engineering guidance only — confirm PDPA policy wording with a professional.

Backfill: dedupe distinct `referrer_email` from `referrals` into `referral_partners` (status `active`), repoint; keep denormalized `referrer_*` nullable during transition.

Commission stays **derived** (`commission_pct` × collected order value), never stored.

**Acceptance:** an inquiry after visiting `/?ref=CODE` (with consent) is tagged to that referrer; without a ref it reads "Public"; a second link visit doesn't override the first-touch code.

---

### Phase 3 — Staff `/team` workspace + grouped IA

Two parts; both frontend-led. **Ships before the partner portal** (team first).

#### 3a. Grouped sidebar (prepare first — quick visible win)

Refactor `adminNav.ts` from flat `AdminNavItem[]` into `NavGroup[]`, ordered by workflow:

```ts
export interface NavGroup { label: string; roles?: Role[]; items: AdminNavItem[] }
export interface AdminNavItem { to: string; label: string; icon: string; matchPrefix?: string; roles?: Role[] }

export const adminNav: NavGroup[] = [
  { label: 'Overview',       items: [ /* Dashboard */ ] },
  { label: 'Sales pipeline', items: [ /* Inquiries, Quotations, Orders, Clients */ ] },
  { label: 'Billing',        items: [ /* Invoices, Payments */ ] },
  { label: 'Growth',         items: [ /* Referrals, Analytics */ ] },
  { label: 'Catalog',        items: [ /* Services, Projects */ ] },
  { label: 'Business', roles: ['founder','partner'], items: [
      { to: '/admin/users',     label: 'Users',     icon: 'i-lucide-user-cog', roles: ['founder'] },
      { to: '/admin/activity',  label: 'Activity',  icon: 'i-lucide-history' },
      { to: '/admin/investors', label: 'Investors', icon: 'i-lucide-handshake' },
  ]},
]
```
Rules: two-level role filter (group + item); collapsible groups with persisted open/closed state; active item's group stays open; muted labels + one accent for active — no per-group colors or per-item badges.

Files: `data/adminNav.ts` (refactor), `data/teamNav.ts` (new), sidebar component in `admin.vue`/layout.

#### 3b. `/team` workspace

Reuse — Inquiries and Referrals exist; expose them scoped, don't rebuild.

Files: `pages/team/index.vue` (reuse `portal.vue`) · `pages/team/inquiries/{index,[id]}.vue` · `pages/team/referrals/index.vue` · `pages/team/payslips/index.vue` (Phase 5 data) · `middleware/team-auth.ts` · `routes/api.php` (`/v1/team/*`, `role:workspace`) · `Resources/InquiryTeamResource.php` (no financial fields) · `Team/*` controllers (scoped).

**Acceptance:** a marketer lands on grouped `/team`, triages an inquiry, manages a referral, cannot reach `/admin`; the admin sidebar renders six collapsible groups with correct role filtering.

---

### Phase 4 — Referral partner portal (the fourth surface)

**Goal:** the growth hook — referrers self-onboard, then log in to track leads + earnings and refer more. Needs Phase 0 (auth) + Phase 2 (data model).

**Auth — three surfaces, two backends.** `/admin` + `/team` share the `users` guard; `/partners` uses a **separate `referral` guard** on `referral_partners`. A leaked partner login can never reach `/admin` or `/team`.

Make `referral_partners` authenticatable: migration adds `password` (hashed, nullable) + `last_login_at`; `Referrer` implements `Authenticatable` + `HasApiTokens`; register a `referral` guard in `config/auth.php`.

**Onboarding flow (approve-first — see the referral_form_flow diagram):**
1. **Public form** on the partner page (unauthenticated, rate-limited, optional captcha for spam). One submit does two things:
   - creates/reuses the `Referrer` (match on email) as `status = pending`;
   - creates a `Referral` (the company referred), visible in the Referrals tab **immediately**.
2. **Staff approval** (marketer): `POST /v1/team/referral-partners/{id}/approve` → sets `status = active`, generates a passcode, emails it. **The passcode email only fires on approval.**
3. **Returning referrer** logs in and uses the in-portal "refer another company" form (account already bound) — same fields, no re-onboarding.

**Passcode:** `Str::password(16)` (CSPRNG), stored `Hash::make()`, shown once via email, **never rendered on a staff screen**, never logged. No self-service reset — staff regenerate via `POST /v1/team/referral-partners/{id}/reset-passcode` → new passcode emailed. Login `POST /v1/partner/login` rate-limited like `$loginThrottle`; token scoped read-only to own data, with expiry.

**Partner page (simple, read-mostly):** headline earned (from collected `payments` on converted orders) + pending; their referrals list with status pills; their `?ref` link to copy + the "refer another" form. Commission derived; **payout stays manual** (Section 3).

Files: `migrations/xxxx_add_partner_auth_to_referral_partners.php` · `config/auth.php` (edit) · `Models/Referrer.php` (edit) · `Partner/AuthController.php` · `Partner/DashboardController.php` · `Team/ReferralPartnersController.php` (create/approve/reset) · `Mail/PartnerPasscodeMail.php` · `routes/api.php` (`/v1/partner/*`) · `pages/partners/{login,index}.vue` · `layouts/partner.vue` · `middleware/partner-auth.ts` · public onboarding form component.

**Acceptance:** submitting the public form creates a `pending` referrer + an immediate lead but sends no email; after marketer approval the passcode email fires and the referrer can log in at `/partners`; they see only their own leads + earnings; staff can reset but the partner can't self-reset; a partner token is rejected on `/v1/admin/*` and `/v1/team/*`.

---

### Phase 5 — In-system ledgers (payroll + marketing spend, record-only)

**Goal:** one source of truth for team money as headcount grows. **Record-only** — no statutory calculation.

```php
Schema::create('payroll_entries', function (Blueprint $t) {
    $t->id();
    $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $t->string('period_label', 40);    // 'Jun 2026'
    $t->unsignedInteger('gross_myr');   // record only
    $t->timestamp('paid_at')->nullable();
    $t->string('method', 40)->nullable();
    $t->text('note')->nullable();
    $t->foreignId('created_by')->constrained('users');
    $t->timestamps();
});
Schema::create('marketing_expenses', function (Blueprint $t) {
    $t->id();
    $t->foreignId('entered_by')->constrained('users');
    $t->string('category', 60);
    $t->unsignedInteger('amount_myr');
    $t->date('spent_at');
    $t->text('note')->nullable();
    $t->timestamps();
});
```
- Payroll: founder creates for anyone + sees all (Gate `view-all-payroll`); everyone else `GET /v1/team/payslips` → `where('user_id', auth id)`.
- Marketing spend: marketer enters + sees own; founder + partner see all.

Files: the two migrations · `{Admin,Team}/PayrollController.php` · `Admin/ExpensesController.php` · `pages/admin/payroll/*` · `pages/team/payslips/index.vue`.

**Acceptance:** marketer records a spend and sees only their own; partner sees full spend roll-up but only their own payslip; founder sees everything.

---

## 6. Build order

```
Phase 3a  Grouped sidebar ....... prepare first (frontend-only quick win); re-check at the end
Phase 0   Foundation ........... roles + tiers + gates + provisioning   (unlocks all)
Phase 1   Attribution .......... updated_by + activity_log + collected-revenue report (THE ASSET)
Phase 2   Referral data model .. normalize + ?ref cookie intake + consent   (needs 0; feeds 1)
Phase 3b  /team workspace ...... scoped surface                          (needs 0)
Phase 4   Partner portal ....... isolated auth + onboarding + dashboard   (needs 0 + 2)  [team-first: after 3]
Phase 5   In-system ledgers .... payroll + marketing spend, record-only   (needs 0 + 3)
—— defer / manual ——
Payout processing ............... manual via commission-email flow
Statutory payroll ............... Malaysian payroll SaaS at ~5+ staff
Investor CRM .................... later
```

---

## 7. File / directory map (new + modified)

```
backend/
  app/Http/
    Middleware/CheckRole.php                      ~ rewrite (tiers)
    Controllers/Api/V1/
      Admin/UsersController.php                   + provisioning
      Admin/ActivityController.php                + activity feed
      Admin/AnalyticsController.php               ~ attribution query (collected rev)
      Admin/ExpensesController.php                + marketing spend (see all)
      Admin/PayrollController.php                 + payroll (see all)
      Team/InquiriesController.php                + scoped
      Team/ReferralsController.php                + scoped
      Team/ReferralPartnersController.php         + create / approve / reset passcode
      Team/PayrollController.php                  + own payslips
      Partner/AuthController.php                  + partner login/logout/me
      Partner/DashboardController.php             + own leads + earnings
      InquiryController.php                        ~ resolve axn_ref cookie / ?ref
    Resources/InquiryTeamResource.php             + scoped
  app/Models/
    User.php                                       ~ roles/tiers helpers
    Referrer.php                                   + referral_partners, Authenticatable + HasApiTokens
    Referral.php, Inquiry.php                      ~ relations
  app/Mail/PartnerPasscodeMail.php                + passcode email
  app/Providers/AppServiceProvider.php            ~ gates
  app/Support/RecordsActivity.php                 + trait
  config/sanctum.php                              ~ token expiry
  config/auth.php                                 ~ referral guard
  database/migrations/                            + ~13 new migrations
  routes/api.php                                  ~ /v1/admin (cockpit) + /v1/team + /v1/partner

frontend/app/
  pages/
    partners/{login,index}.vue                    + partner portal
    public/legal/cookies.vue                      + cookie policy
    team/index.vue                                + shell (reuse portal.vue)
    team/inquiries/{index,[id]}.vue               + scoped
    team/referrals/index.vue                      + marketer
    team/payslips/index.vue                       + own payslips
    admin/activity/index.vue                      + feed
    admin/users/index.vue                         + provisioning UI
    admin/payroll/index.vue                       + payroll ledger
  layouts/partner.vue                             + minimal partner layout
  components/CookieConsent.vue                     + consent banner
  components/ReferralForm.vue                      + public + in-portal (context-aware)
  data/adminNav.ts                                ~ flat → NavGroup[] (6 groups)
  data/teamNav.ts                                 + role-filtered workspace nav
  middleware/{team-auth,partner-auth}.ts          + guards
```

Already on main (referenced, no work): `invoices`/`receipts`/`payments`/`gateway_events` + models + controllers; `AnalyticsController`; `service_addons` / `service_scope_fields`; `public/legal` area.

---

## 8. Explicitly out of scope

- Commission payout / funds-transfer engine → paid manually via commission-email flow.
- Statutory payroll calculation → payroll SaaS.
- In-app team chat → Slack / WhatsApp / Notion.
- Separate app or repo for staff or partners.

## 9. Settled defaults + remaining micro-decisions

**Settled:** four roles / two tiers · marketer manages the referral programme · `Referrer`/`referral_partners` + `Referral` naming · team before partner portal · sidebar prepared first · ledgers in-system, record-only · approve-first onboarding · two-context referral form · `?ref` query param + `axn_ref` functional cookie + consent · first-touch credit.

**Micro-decisions (won't block anything):**
1. Cookie window exact length (60 vs 90 days) — align to your commission validity.
2. Spam control on the public referral form — rate-limit only, or add a captcha?
3. Whether Investors/Analytics placeholders get finished in this cycle or later.
```
