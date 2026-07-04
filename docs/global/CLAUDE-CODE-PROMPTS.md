# CLAUDE-CODE-PROMPTS

Companion to `DASHBOARD-REVAMP-PLAN.md`. One prompt per phase, in build order.

**How to use:**
- Put `DASHBOARD-REVAMP-PLAN.md` in the repo at `docs/global/DASHBOARD-REVAMP-PLAN.md` (or edit the path in each prompt).
- Run **one phase per Claude Code session.** Don't paste multiple phases at once.
- After each phase: review the diff, run the migrations/build, check the acceptance criteria, commit. Then start a fresh session for the next.
- Each prompt tells Claude Code to read existing files first so its output mirrors your patterns instead of inventing new ones.

---

## Session 1 — Phase 3a (grouped sidebar)

```
Read docs/global/DASHBOARD-REVAMP-PLAN.md for full context.

Before writing anything, read frontend/app/data/adminNav.ts and the sidebar
component in the admin layout (frontend/app/pages/admin.vue or the layout it uses),
plus one existing admin page, so your code matches the existing Nuxt/Tailwind style.

Implement Phase 3a ONLY — the grouped sidebar:
- Refactor adminNav.ts from a flat AdminNavItem[] into NavGroup[] with the six groups
  in the plan (Overview, Sales pipeline, Billing, Growth, Catalog, Business).
- Update the sidebar component to render group labels + collapsible groups with
  persisted open/closed state; keep the active item's group expanded.
- Muted section labels, one accent for the active item. No per-group colors or badges.

Do not build any other phase. Do not add backend changes. Stop when the sidebar renders
the six groups and the acceptance criteria for Phase 3a pass. Show me the diff.
```

---

## Session 2 — Phase 0 (foundation)

```
Read docs/global/DASHBOARD-REVAMP-PLAN.md for full context.

FIRST: the plan's "Current repo state" reflects an earlier pull. Diff against current main —
check the actual users migration, CheckRole, routes/api.php, and confirm nothing here is
already done. Report anything that differs before you start.

Then read backend/app/Http/Middleware/CheckRole.php, backend/app/Models/User.php,
backend/routes/api.php, and one existing Admin controller, to mirror the existing style.

Implement Phase 0 ONLY — foundation:
- Role enum migration (founder/partner/marketer/engineer) + set the existing admin to founder.
- User tier/role helpers. Rewrite CheckRole to the tier-aware version in the plan.
- The four founder-only Gates + Gate::authorize calls in the matching controller actions.
- Route gating: /v1/admin/* -> role:cockpit.
- UsersController provisioning (create / change role / deactivate) + Sanctum token expiry.

Respect Section 3 of the plan (do-not-build list) — if anything seems to need it, stop and ask.
Do not build other phases. Stop when Phase 0's acceptance criteria pass. Show me the diff and
tell me how to run the migration.
```

---

## Session 3 — Phase 1 (attribution)

```
Read docs/global/DASHBOARD-REVAMP-PLAN.md for full context.

Read one existing controller that does a status update (e.g. Admin/QuotationsController),
the Admin/AnalyticsController, and a recent migration, to match style.

Implement Phase 1 ONLY — attribution:
- updated_by nullable FK on the business tables listed in the plan.
- activity_log table + a RecordsActivity trait; call it from state-changing actions
  (status, payment, accept, CRUD). Gateway/webhook writes log actor_id = null.
- Attribution query in AnalyticsController grouping collected payments by inquiry source +
  referrer; expose GET /v1/admin/analytics/attribution.
- An Activity feed page under /admin (Business group) + "last updated by" on detail pages.

Do not build other phases. Respect Section 3. Stop when Phase 1's acceptance criteria pass.
Show me the diff.
```

---

## Session 4 — Phase 2 (referral data model + attribution intake)

```
Read docs/global/DASHBOARD-REVAMP-PLAN.md for full context.

Read the referrals and inquiries migrations + models, backend Api/V1/InquiryController.php,
and the frontend public/legal area, to match style.

Implement Phase 2 ONLY — referral data model + intake:
- referral_partners table (status enum includes 'pending'); Referrer model.
- referral_partner_id nullable FK on referrals and inquiries; relations.
- Backfill distinct referrers from existing referrals into referral_partners, then repoint.
- ?ref={code} capture -> first-party functional cookie axn_ref (60-90 day window);
  InquiryController resolves it to referral_partner_id + source='referral'; null = public.
- First-touch wins (don't overwrite an existing cookie code).
- Cookie consent banner + a cookie-policy page under public/legal; don't set the cookie
  before consent; fall back to reading ?ref server-side at submit if consent declined.

Keep commission derived, never stored. Do not build the partner portal UI (that's Phase 4).
Respect Section 3. Stop when Phase 2's acceptance criteria pass. Show me the diff.
```

---

## Session 5 — Phase 3b (/team workspace)

```
Read docs/global/DASHBOARD-REVAMP-PLAN.md for full context.

Read frontend/app/pages/portal/[token] (the portal stub), one existing admin list+detail
page with its API Resource, and data/adminNav.ts (now grouped), to match style.

Implement Phase 3b ONLY — the /team workspace:
- /team shell reusing the portal.vue layout + team-auth middleware (requires tier=workspace).
- teamNav.ts (role-filtered) reusing the NavGroup structure from Phase 3a.
- /v1/team/* route group gated role:workspace; scoped controllers/resources that OMIT
  financial fields (InquiryTeamResource).
- Pages: team inquiries (triage/respond), team referrals (marketer). Payslips page is a
  stub until Phase 5.

Reuse existing controllers where the action is identical; only the resource differs.
Do not build the partner portal or ledgers. Respect Section 3. Stop when Phase 3b's
acceptance criteria pass. Show me the diff.
```

---

## Session 6 — Phase 4 (referral partner portal)

```
Read docs/global/DASHBOARD-REVAMP-PLAN.md for full context.

Read backend/config/auth.php, the existing Admin/AuthController, an existing Mail class,
and the Sanctum setup, to match style. Re-read Phase 2's referral_partners schema.

Implement Phase 4 ONLY — the referral partner portal (isolated auth):
- Add password (hashed) + last_login_at to referral_partners; Referrer implements
  Authenticatable + HasApiTokens; register a separate 'referral' guard in config/auth.php.
- Public referral form (unauthenticated, rate-limited): one submit creates/reuses a
  Referrer as status=pending AND creates a Referral (lead) shown immediately.
- Marketer approval action -> status=active, generate Str::password(16), Hash::make, email
  it via PartnerPasscodeMail. The passcode email fires ONLY on approval. Never render the
  passcode on a staff screen; never log it.
- No self-service reset; staff reset-passcode regenerates + emails.
- /v1/partner/login (rate-limited) + /v1/partner/* behind the referral guard; tokens scoped
  read-only to own data, with expiry.
- /partners portal: earned (from collected payments) + pending, own referrals with status,
  their ?ref link, and a context-aware "refer another" form for logged-in referrers.

CRITICAL from Section 3: do NOT build any payout/funds-transfer. Commission is derived and
displayed only. Verify a partner token is rejected on /v1/admin/* and /v1/team/*.
Stop when Phase 4's acceptance criteria pass. Show me the diff.
```

---

## Session 7 — Phase 5 (in-system ledgers)

```
Read docs/global/DASHBOARD-REVAMP-PLAN.md for full context.

Read one simple existing CRUD controller + its migration, and the /team pages from Phase 3b,
to match style.

Implement Phase 5 ONLY — record-only ledgers:
- payroll_entries + marketing_expenses tables per the plan.
- Payroll: founder creates for anyone + sees all (Gate view-all-payroll); everyone else
  GET /v1/team/payslips -> own rows only. Wire the team payslips page.
- Marketing spend: marketer enters + sees own; founder + partner see all.

CRITICAL from Section 3: record-only. No EPF/SOCSO/EIS/PCB or any statutory calculation —
amounts are entered, never computed. Respect Section 3. Stop when Phase 5's acceptance
criteria pass. Show me the diff.
```

---

## After all phases

Re-check Phase 3a — with Users, Activity, and the new surfaces in place, confirm the grouped
sidebar still reads cleanly and the role filtering hides the right groups/items per role.
```
