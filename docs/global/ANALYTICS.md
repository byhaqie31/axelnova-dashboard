# Analytics (Phase B)

Traffic + engagement signals for the admin. Privacy-first by design: raw IPs are
never stored, no cookie is needed for views, and likes are anonymous. Spans both
apps — collection in the public frontend, aggregation in the Laravel backend,
display in the `/admin/analytics` page + the dashboard tile.

> Status: **slices 1 & 2 shipped** (page views, likes). Slices 3 & 4 (service
> interest, quote funnel) are scoped below but not yet built.

---

## The model

Two append-only-ish tables (migration `2026_05_08_010004_create_analytics_tables`):

- **`page_views`** — `path`, `ip_hash`, `user_agent`, `referrer`, `viewed_at`.
  One row per public page view. No `updated_at` (cheap inserts).
- **`entity_likes`** — `entity_type` (`project` | `service_package`), `entity_id`,
  `ip_hash`, `cookie_id?`, `created_at`. Unique on
  `(entity_type, entity_id, ip_hash)` → one like per visitor per entity.

**IP hashing** — [`App\Support\AnalyticsHash::forIp`](../../backend/app/Support/AnalyticsHash.php)
returns `sha256(ip + app key)`. Stable (so unique-visitor counts and the like
constraint work over time) and one-way (raw IP never persisted).

---

## Slice 1 — Page views ✅

**Collection.** [`frontend/app/plugins/pageview.client.ts`](../../frontend/app/plugins/pageview.client.ts)
fires a fire-and-forget `POST /api/v1/track/page-view` on the initial load and
every public SPA navigation. **`/admin` and `/portal` are never tracked.** Errors
are swallowed.

**Write.** [`TrackingController::pageView`](../../backend/app/Http/Controllers/Api/V1/TrackingController.php)
— validates `path`/`referrer`, hashes the IP, drops obvious bots by UA regex,
inserts a row, returns `204`. Route is public + stateless under a generous
throttle (`120/min` prod).

**Read.** [`Admin\AnalyticsController::overview`](../../backend/app/Http/Controllers/Api/V1/Admin/AnalyticsController.php)
— `GET /api/v1/admin/analytics/overview?range=7d|30d` returns:
```
views: { total, unique, series:[{date,count}] }   # unique = distinct ip_hash
topPaths:        [{path, count}]                    # window
topReferrers:    [{referrer, count}]                # window
topLikedProjects:[{id, name, likes}]                # all-time (slice 2)
```

**Display.** [`/admin/analytics`](../../frontend/app/pages/admin/analytics/index.vue)
— Page views + Unique visitors, an SVG bar chart with a 7d/30d toggle, Top pages,
Top referrers. The dashboard "Page views (7d)" tile reads `views.total`
best-effort (a failure leaves the tile as `—`, never breaks the dashboard).

---

## Slice 2 — Likes ✅

**Toggle.** [`LikesController::toggle`](../../backend/app/Http/Controllers/Api/V1/LikesController.php)
— `POST /api/v1/likes/{type}/{id}` (public, throttled). Type allowlist
`project` / `service_package`; 404 if the entity doesn't exist. Deletes the
existing like for this `ip_hash` or creates one; returns `{ liked, count }`.

**Counts.** `Project` + `ServicePackage` expose a `likes()` `hasMany` (scoped to
their entity type); the public controllers `withCount('likes')` and the resources
emit `likes_count`. So the public projects/services payloads carry counts with no
extra request.

**UI.** [`frontend/app/components/shared/LikeButton.vue`](../../frontend/app/components/shared/LikeButton.vue)
— heart + count pill, optimistic toggle reconciled with the server response. The
`liked` state is mirrored in `localStorage` (`axn_likes` map + an `axn_like_cookie`
id) so the filled heart persists across visits without a lookup; the server's
hashed-IP constraint is the real dedupe. SSR-safe (starts unliked, hydrates on
mount). Placed on `ProjectCard` (home + registry, top-right), the project detail
CTA row, and each service package card.

**Admin.** Overview adds an all-time `topLikedProjects` leaderboard, rendered as
"Most-liked projects" on the analytics page.

---

## Privacy

- No raw IP stored — only `sha256(ip + app key)`.
- Page views need no cookie. Likes use a client-generated `cookie_id` (localStorage)
  purely for the visitor's own UI continuity; dedupe is server-side by hashed IP.
- Bots are filtered on write so counts reflect humans.
- Fits the existing `/legal/cookies` + privacy pages.

---

## Roadmap — remaining slices

### Slice 3 — Service interest (not built)
Tie page-view data to service packages so the admin sees which packages draw
attention. Likely: count views whose `path` maps to a package (e.g. `/services`
+ the package slug / a `/services/[slug]` route if added) and combine with
`likes_count` per package. Surface as a "Service interest" table on the analytics
page (replace the Soon card): package · views · likes. Backend: extend
`overview()` (or a dedicated method) with a `serviceInterest` array joining
`page_views` (by path) + `entity_likes` (entity_type=service_package) to
`service_packages`.

### Slice 4 — Quote funnel (not built)
Conversion ratio: views of `/quote` → quote starts → submitted leads. Sources:
`page_views` (path `/quote`) for the top of funnel, and `quote_requests` /
`inquiries` for submissions. May need a lightweight "quote started" beacon event
(e.g. first interaction on `/quote`) to capture the middle step — decide whether
to add an event type to `page_views` (e.g. a `path` sentinel like
`/quote#started`) or a small events table. Surface as a 3-stage funnel on the
analytics page (replace the Soon card).

> Both slices are additive — no schema change required for slice 3; slice 4 may
> want a way to record the "started" step. Keep the same privacy posture.
