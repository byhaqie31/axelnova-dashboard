# Email Phase 2 (credentials by link) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans (inline). Steps use checkbox (`- [ ]`) syntax.

**Goal:** No password or passcode ever travels by email: set-up and reset happen through one-time links, team "forgot password" is self-service, partners choose their own passcode, and the founder can recover the admin account.

**Architecture:** An `access_tokens` table + `AccessLinks` service issue single-use, expiring, hashed links for users and external accounts; one public endpoint pair inspects/redeems them for all three surfaces; the Phase 1 email templates carry the links; the frontend gets one shared form behind three surface pages.

**Tech Stack:** Laravel 11.51, PHPUnit, Nuxt 4 + Vitest.

**Spec:** `docs/superpowers/specs/2026-10-12-email-phase-2-security-design.md`

## Global Constraints

- **NO commits** — owner reviews first. Branch `feat/email-phase-2-security`.
- **DATABASE PROTECTION:** additive `php artisan migrate` only; never fresh/refresh/reset/rollback/wipe/drop/truncate. Tests use `axelnova_dashboard_test`.
- Set-up links 72 hours; reset links 60 minutes (config, not literals in code).
- Team/founder passwords min 12 chars; partner passcodes min 10.
- Responses to forgot-password requests stay generic (no account enumeration).
- Email copy: no password/passcode anywhere; links only. New copy goes on the owner's review list.
- Container commands: `docker compose -f docker-compose.dev.yml exec -T backend …` / `… frontend …`.

## Review Focus

1. A link used twice, after expiry, after a newer link was issued, or for a deactivated/suspended account must be refused with one generic message.
2. Redeeming must sign the account out everywhere and work exactly once even if submitted twice concurrently.
3. A partner "forgot passcode" request must change nothing until the link is used (no lockout).
4. Forgot-password endpoints must not reveal whether an account exists (same response, same status).
5. The founder recovery endpoint must never issue a link for a non-founder account.

---

### Task 1: Access links — table, model, service
**Files:** migration `create_access_tokens_table`, migration `add_password_set_at_to_users_table` (backfill existing rows from `created_at`), `app/Models/AccessToken.php`, `app/Services/Auth/AccessLinks.php`, `config/auth.php` (`access_links`). **Test:** `tests/Feature/Auth/AccessLinksTest.php`.
**Produces:** `AccessLinks::issue(Model $account, string $purpose): string` (URL) · `AccessLinks::inspect(string $token): ?AccessToken` · `AccessLinks::redeem(string $token, string $secret): Model` (throws `InvalidAccessLink`) · `AccessToken::accountKind(): 'team'|'founder'|'partner'`.
- [ ] Tests first: issue → inspect returns it; expiry by purpose; used link refused; reissue invalidates the earlier unused link; deactivated user / suspended partner refused; redeem sets the hashed secret, marks used, deletes Sanctum tokens, stamps `password_set_at`; concurrent double redeem → exactly one succeeds.
- [ ] Implement → green → full suite.

### Task 2: Emails carry links
**Files:** `TeamWelcomeMail`, `TeamPasswordResetMail`, `PartnerPasscodeMail` (queued), new `PartnerResetLinkMail` (#19), new `AdminAccountRecoveryMail` (#32), `PartnerResetRequestedMail` / `TeamPasswordResetRequestedMail` (completion heads-ups), their templates, `EmailCatalog` subjects, `EmailSamples`. **Tests:** update `ExistingEmailsCopyTest`, new `CredentialEmailsTest` (each carries its link + expiry and never a secret; heads-ups name the person).
- [ ] Tests first → implement → green.

### Task 3: Public access-link endpoints
**Files:** `Api/V1/Auth/AccessLinkController.php`, `routes/api.php` (login throttle group). **Test:** `tests/Feature/Auth/AccessLinkEndpointTest.php` — GET shape per account kind; 410 for expired/used/unknown; POST validates min length + confirmation, sets the secret, returns `login_url`; second POST → 410.

### Task 4: Team flows
**Files:** `Team/AuthController::forgotPassword` (self-service link), `Admin/UsersController::store` (no password; set-up link) + `resetPassword` (set-up vs reset link; no password in response), heads-up on completion (hook in `AccessLinks::redeem` for `reset`). **Tests:** update `AdminUsersTest`, `TeamAuthTest`.

### Task 5: Partner flows
**Files:** `Admin/ReferralPartnersController::approve|resetPasscode`, `Partner/AuthController::forgotPasscode` (link only, no rotation), `syncAccount` without a passcode. **Tests:** update `PartnerAuthTest` + referral-partner tests; forgot changes nothing until redeemed.

### Task 6: Founder recovery
**Files:** `Admin/AuthController::forgotPassword` (founder only, generic response), route. **Test:** `tests/Feature/Auth/AdminRecoveryTest.php`.

### Task 7: Frontend
**Files:** `components/shared/AccessLinkForm.vue`, `utils/accessLink.ts` (+ test: password rules per account kind), pages `team/set-password.vue`, `partners/set-passcode.vue`, `admin/reset-password.vue`, `admin/forgot.vue`; edits to `admin/login.vue`, `team/forgot.vue`, `partners/forgot.vue`, `admin/users/index.vue`, `admin/users/[id].vue`. Gates: ESLint, typecheck, Vitest.

### Task 8: Docs
`docs/global/EMAIL-SYSTEM.md` (statuses 18/19/20/21/26/27/32 live, credential flow section), `ARCHITECTURE.md` (table, routes), any doc describing passcode delivery.

### Task 9: Verification + review
Pint, PHPUnit, ESLint, typecheck, Vitest; fresh-context review of the branch (security focus); fix Critical/Important test-first.
