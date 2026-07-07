# Auth: kick to login on API unauthorized (401)

**Date:** 2026-07-08
**Branch:** feat/quotation-builder-v2
**Status:** Design approved, spec under review

## Problem

Across all three authenticated frontend surfaces — admin (`/admin`), team (`/team`),
and partner (`/partners`) — a stale, expired, or revoked bearer token leaves the user
sitting on a broken/empty page instead of being sent to the login screen. Reported
symptom: "the API shows unauthorized/invalid token, but the page still remains."

### Root cause

1. **Route guards check token *presence*, not *validity*.**
   [`admin-auth.ts`](../../../frontend/app/middleware/admin-auth.ts),
   [`team-auth.ts`](../../../frontend/app/middleware/team-auth.ts), and
   [`partner-auth.ts`](../../../frontend/app/middleware/partner-auth.ts) each do
   `localStorage.getItem(<key>)` → if present, allow through. A dead token passes the guard.

2. **The shared `apiFetch` has no response-error interceptor.**
   [`useAdminAuth.ts`](../../../frontend/app/composables/useAdminAuth.ts) (and the team/partner
   twins) attach the `Authorization: Bearer` header but never inspect the response. When the
   backend answers **401 Unauthenticated**, the promise rejects and pages swallow it (empty
   `catch`, or a silent `useAsyncData` error), so the stale page stays rendered.

3. There is **zero** 401 handling anywhere in the app today (grep: no `status === 401`,
   no `onResponseError`).

## Decisions (confirmed with user)

- **Scope:** all three surfaces — admin, team, partner (identical bug, identical pattern).
- **Expiry UX:** redirect to login carrying `?redirect=<where-they-were>` **and** show a
  "session expired" notice on the login page. Re-login returns them to the original page.
- **"Token activation":** means general token lifecycle handling. No separate activation
  feature. The reactive 401 interceptor + guards cover it. **No** proactive `/me` validation.

## Approach

Chosen: **shared factory + response interceptor** (Approach A).

Rejected alternatives:
- **B — interceptor added to each `apiFetch` separately:** re-triplicates the 401 logic —
  the exact copy-paste that caused this gap in all three. Rejected.
- **C — global Nuxt `$fetch` plugin:** the surfaces use different token keys + login routes,
  and public fetches (quote builder, portal) must NOT bounce on 401. A global interceptor
  can't tell surfaces apart without per-request tagging → more complexity/blast radius. Rejected.

## Design

### 1. New composable — `app/composables/useTokenAuth.ts` (shared factory)

`createTokenAuth({ tokenKey, loginPath })` returns
`{ getToken, setToken, clearToken, authHeaders, apiFetch }`.

`apiFetch` is built so that on a response error it runs an `onResponseError` handler that,
for status **401** (defensively also **419**), performs an *involuntary logout*:

- **Guards (all must hold) before redirecting:**
  - running client-side (`import.meta.client`) — token-bearing requests only happen client-side;
  - a token **was** stored at the time of the failure (so a bad-credentials login, which has no
    token yet, is left untouched and keeps showing "Invalid credentials");
  - the current route is not already the `loginPath` (no self-redirect loop);
  - a module-level `redirecting` flag is not already set.
- **Action:** set the `redirecting` flag → `clearToken()` →
  `navigateTo({ path: loginPath, query: { redirect: <current fullPath>, expired: '1' } })` →
  reset the `redirecting` flag after navigation settles.
- The rejection still propagates so callers do not proceed with `undefined` data.

The `redirecting` flag lives at module scope so that a dashboard firing several parallel
requests that all 401 triggers exactly **one** bounce, not many.

### 2. Refactor the three auth composables onto the factory

[`useAdminAuth.ts`](../../../frontend/app/composables/useAdminAuth.ts),
[`useTeamAuth.ts`](../../../frontend/app/composables/useTeamAuth.ts),
[`usePartnerAuth.ts`](../../../frontend/app/composables/usePartnerAuth.ts) each call
`createTokenAuth` with their own config:

| Composable      | tokenKey            | loginPath          | extras kept                                  |
|-----------------|---------------------|--------------------|----------------------------------------------|
| `useAdminAuth`  | `axn_admin_token`   | `/admin/login`     | `logout()` (local clear + nav), `jumpToTeam` |
| `useTeamAuth`   | `axn_team_token`    | `/team/login`      | `logout()` (best-effort server revoke + clear + nav) |
| `usePartnerAuth`| `axn_partner_token` | `/partners/login`  | `logout()` (best-effort server revoke + clear + nav) |

The **public API of each composable stays identical** (`getToken`, `setToken`, `clearToken`,
`authHeaders`, `apiFetch`, `logout`, plus `jumpToTeam` on admin). None of the ~54 call sites
change.

Voluntary `logout()` is unchanged behavior; the interceptor only covers *involuntary* expiry.

### 3. Login pages — "session expired" notice + return path

For each of [`admin/login.vue`](../../../frontend/app/pages/admin/login.vue),
[`team/login.vue`](../../../frontend/app/pages/team/login.vue),
[`partners/login.vue`](../../../frontend/app/pages/partners/login.vue):

- Read `route.query.expired === '1'` and render an inline banner
  ("Your session expired — please sign in again.") reusing the existing error styling.
- Ensure each honors `?redirect=` on successful login. Admin already does
  ([`admin/login.vue:21-24`](../../../frontend/app/pages/admin/login.vue#L21-L24)) with an
  open-redirect guard (`startsWith('/admin')`, not `//`). Verify team/partner; add the same
  guarded `redirectTo` computed (prefix `/team`, `/partners` respectively) where missing.

## Out of scope

- Proactive `/me` token validation in middleware (user chose general handling; the reactive
  interceptor catches stale tokens the instant any protected page fetches on mount).
- The dead cookie-based [`useApi.ts`](../../../frontend/app/composables/useApi.ts) (0 usages) —
  left as-is; no unrelated cleanup.

## Verification

For **each** surface (admin, team, partner):

1. **Expired/invalid token + navigate** → bounced to that surface's login with the "session
   expired" notice; after re-login, land back on the originally requested page.
2. **Bad credentials on login** → still shows "Invalid credentials"; **no** expired banner,
   **no** redirect (proves the "token was present" guard works).
3. **Valid session** → no bounce; normal use.

Plus repo gates: `vue-tsc` typecheck clean, ESLint clean.

## Notes

- Per standing user preference, the spec and code are **not** committed until explicitly requested.
