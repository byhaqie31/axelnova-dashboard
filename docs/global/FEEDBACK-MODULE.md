# Feedback & Reviews

Client feedback pipeline: a token-gated public form, an admin moderation
module, and a consent-gated testimonial wall on the public site. One review
per order, nothing auto-publishes, and the client's words never appear
publicly without their explicit permission.

---

## Data model

One table — `feedback` (soft-deletes). `ORDERS ||--o| FEEDBACK : "reviewed by"`.

| Group | Columns | Notes |
|---|---|---|
| Identity | `reference_code` | `AXNF-YYYY-NNNN`, minted by [`ReferenceCodeGenerator`](../../backend/app/Support/ReferenceCodeGenerator.php) via `DocumentType::Feedback` — same atomic lock/transaction path as every AXN code, own yearly counter |
| Anchor | `order_id` (unique, nullable), `client_id` | One feedback per order; NULL allowed so admin can log standalone feedback. `client_id` is denormalised for display |
| Access | `public_token` (48 chars, unique) | The only credential for the public page — `Feedback::mintToken()` (uniqueness-checked `Str::random(48)`) |
| Snapshot | `name`, `email`, `project_label` | Display copies taken at create time (from the order's client in request mode) |
| Scores | `overall` (1–5), `rating_design` / `rating_communication` / `rating_delivery` / `rating_value` (1–5), `nps` (0–10) | Only `overall` is required on submit. Accessors: `average_rating` (mean of non-null dimensions, 1dp), `nps_bucket` (`promoter` ≥9 / `passive` 7–8 / `detractor` ≤6) |
| Text | `praise`, `improve` | "What we got right" / "where to improve", max 2000 chars each |
| Consent | `publish_consent`, `attribution_name`, `attribution_role` | Attribution name is **required only when consent is on** (the wall needs a byline) |
| Lifecycle | `status`, `source`, `featured`, `sort_order` | See below. `source`: `admin` for both admin create modes; `self_serve` is the default reserved for a future public-initiated flow |
| Audit | `submitted_at`, `reviewed_at`, `published_at` | Submit locks resubmission; first admin open of a pending row stamps `reviewed_at`; first publish stamps `published_at` (kept across archive → republish) |

## Status lifecycle — nothing auto-publishes

```
pending ──→ approved ──→ published
   │            │            │
   └────────────┴────────────┴──→ archived (any time)
```

- Client submissions **always** land (and stay) `pending` — an admin reviews
  every word before anything is visible.
- **Publish gate:** transitioning to `published` requires
  `publish_consent = true`. Enforced server-side in
  [`Admin\FeedbackController::guardTransition()`](../../backend/app/Http/Controllers/Api/V1/Admin/FeedbackController.php)
  on **both** write paths (`POST /status` and `PUT`), returning 422 without it.
- `published_at` stamps once; archive → republish keeps the original date.

## Token flow

1. Admin creates a feedback row (`mode=request`) → `public_token` + `AXNF`
   code minted, `RequestFeedbackJob` queued (**database queue — the worker
   must be running**, same as quote emails).
2. `FeedbackRequestMail` (markdown `mail.feedback-request`) sends the client a
   CTA to `{PUBLIC_SITE_URL}/feedback/{token}`. Subject: *"How did we do? —
   quick feedback on your Axel Nova project"*. No attachments. Silently
   skipped when the row has no email.
3. The client opens `/feedback/{token}` — `layout: false`, `noindex`, in the
   sitemap exclude list. `GET /v1/feedback/{token}` returns only the shell
   (name, project label, already-submitted flag); unknown tokens 404.
4. `POST /v1/feedback/{token}` writes the scores **once** — a second submit is
   refused 409 (`submitted_at` is the lock). Throttled **5/hour/IP in
   production** (env-aware, own throttle group — mirrors the quote-funnel
   pattern in [routes/api.php](../../backend/routes/api.php)).

## Admin module (`/admin/feedback`, Growth group)

| Endpoint | Purpose |
|---|---|
| `GET /v1/admin/feedback` | List — search (ref/name/email), status filter, paginated, plus a `stats` block (total / pending / published / avg overall) for the index tiles |
| `POST /v1/admin/feedback` | Create — `mode=request` (needs `order_id`, emails the link) or `mode=log` (offline feedback, scores entered directly, born submitted) |
| `GET /v1/admin/feedback/{id}` | Detail; stamps `reviewed_at` on first open of a pending row |
| `PUT /v1/admin/feedback/{id}` | Edit/moderate (attribution, label, featured, consent, sort order; status passes the same publish guard) |
| `POST /v1/admin/feedback/{id}/status` | Transition (consent-gated publish) |
| `DELETE /v1/admin/feedback/{id}` | Soft delete |

Ordering uses the shared [`SortOrder`](../../backend/app/Support/SortOrder.php)
helper (global scope) and the §12.3 pill picker in the UI — never a number
input. Scores are **read-only** on the detail page (the review is the client's
record); only attribution/label/flags are editable after creation.

## Testimonial wall

- `GET /v1/testimonials` — public, **cached 1h** under
  **`public_testimonials_v1`**. Returns only rows with
  `status = published AND publish_consent = true`, ordered
  `featured desc, sort_order asc, published_at desc`, and exposes only the
  wall-safe fields: `attribution_name`, `attribution_role`, `project_label`,
  `overall`, `praise`.
- [`FeedbackObserver`](../../backend/app/Observers/FeedbackObserver.php)
  (registered in `AppServiceProvider`) forgets that cache key on every
  save/delete — same shape as `ServicePackageObserver`.
- Frontend: [`TestimonialWall.vue`](../../frontend/app/components/public/TestimonialWall.vue)
  on the home page (below the mockups, above the closing CTA). Renders
  **nothing** when the feed is empty.

## Request trigger

Admin-initiated only ("Request from client" on `/admin/feedback/new`). The
order-completion auto-send is deliberately **not** wired — a `// TODO(feedback)`
hook sits in `OrdersController::updateStatus()` for a later pass.

## File map

- Backend: `app/Models/Feedback.php`, `app/Http/Controllers/Api/V1/{Feedback,PublicTestimonials}Controller.php`,
  `app/Http/Controllers/Api/V1/Admin/FeedbackController.php`,
  `app/Http/Requests/PublicFeedbackRequest.php` + `Admin/AdminFeedback{Store,Update}Request.php`,
  `app/Observers/FeedbackObserver.php`, `app/Jobs/RequestFeedbackJob.php`,
  `app/Mail/FeedbackRequestMail.php`, `resources/views/mail/feedback-request.blade.php`
- Frontend: `app/pages/public/feedback/[token].vue`, `app/pages/admin/feedback/{index,[id]}.vue`,
  `app/components/shared/FeedbackScale.vue`, `app/components/public/TestimonialWall.vue`,
  `app/data/feedbackStatuses.ts`
- Tests: `backend/tests/Feature/Feedback/FeedbackModuleTest.php`
