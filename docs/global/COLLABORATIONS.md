# Collaborations — studio partners (`/collaborations`)

Public pages for the studios Axel Nova Ventures builds **with**. The first is
**TRLabs** ([trlabs.my](https://trlabs.my), Thaqif Rosdi), launched as the
co-branded **Axel Nova × TRLabs** page.

> **Not the referral programme.** `/partners` is the commission-based Partner
> Program (`referral_partners`, the partner portal). A *collaboration partner*
> is a studio we co-build, subcontract or swap referrals with. The two never
> share tables, routes or wording. The `/partners` landing links here for
> studios, and `/collaborations` links back for referrers.

The pages serve three audiences:

- **Clients:** proof that Axel Nova can take on wider projects with trusted studios.
- **Partners:** a profile page that promotes the partner studio.
- **Studios:** a "Partner with us" application.

## What exists today (v1: TRLabs, seeded)

| Piece | Where |
|---|---|
| Partner profiles | `collaboration_partners` table, `App\Models\CollaborationPartner` |
| Shared projects | `collaboration_partner_project` link table → existing `projects` rows |
| Public API | `GET /v1/collaborations`, `GET /v1/collaborations/{slug}` (`PublicCollaborationsController`) |
| Project credit | `GET /v1/projects/{slug}` → `partners[]` (the "Built with TRLabs" chip on `/projects/[id]`) |
| Leads | `POST /v1/inquiries` with `origin=partner` / `origin=collaboration` |
| Pages | `/collaborations` (index), `/collaborations/trlabs` (hand-built, co-branded) |
| Seed | `2026_10_11_200004_seed_trlabs_collaboration_partner.php` (data migration) |

**There is no admin editor yet,** deliberately: there is one partner. Partner
rows and project links change through a **new data migration** (see below).
The admin CRUD and a generic `/collaborations/[slug]` template come with the
second partner.

## Data model

### `collaboration_partners`

| Column | Notes |
|---|---|
| `slug` | URL key: `/collaborations/{slug}` and the scope class `collab-{slug}`. Unique, including soft-deleted rows |
| `name`, `tagline`, `summary` (≤ 200), `story` | `story` is plain text; paragraphs separated by a blank line |
| `logo_url`, `symbol_url` | Static files under `frontend/public/logos/`, stored as paths (the same rule as `projects.cover_image_url`). `logo_url` is the wide wordmark; `symbol_url` is the square mark used in lockups and chips |
| `website_url` | Shown as a plain outbound link in the profile facts. It is the page's only outbound link |
| `founder_name`, `founder_role`, `location` | Profile facts |
| `services` | JSON list of what the partner builds |
| `partner_since` | Date. The site shows the year only ("Since 2026") |
| `active`, `sort_order` | Hidden until active (DB default `false`). Soft deletes |

### `collaboration_partner_project`

| Column | Notes |
|---|---|
| `collaboration_partner_id`, `project_id` | Unique pair. Deleting either side removes the link |
| `mode` | `co_build` · `subcontract` · `referral`: how the studios worked |
| `role_note` | ≤ 200 chars, e.g. "TRLabs: UI + mobile app · Axel Nova: backend + admin" |
| `credit_public` | Whether the site may name the partner on this project |

**Visibility rule (server-enforced):** a shared project appears publicly, both
on the partner page and as the project's "Built with" chip, only if **all
three** hold:

1. the partner is `active` and not deleted;
2. the link's `credit_public` is on;
3. the project itself is `active`.

**Credit defaults:** `CollaborationPartner::linkProject()` sets `credit_public`
**on only for `co_build`**. Subcontracted and referred work is often
confidential to the end client (the partner may have white-labelled), so
naming the partner on it is an explicit opt-in (`creditPublic: true`). Always
link through `linkProject()`. It also rejects unknown modes.

### `inquiries` (additive)

- `origin` gains `partner` and `collaboration`:
  - **`partner`:** a client hiring a partner studio *through* Axel Nova. The form sends `partner: {slug}`, which must be an active partner. The server resolves it to `collaboration_partner_id` (nullOnDelete). The lead is logged here and passed on to the partner manually; partners have no login and get no automatic email.
  - **`collaboration`:** a studio applying to partner. The optional `portfolio_url` is validated as a URL and appended to the message (`Portfolio: …`). There is no column for it.
- The partner enquiry form's field is `partner`, not `website_url`, because `website_url` is the inquiry form's spam **honeypot**.
- Emails reuse the existing pair:
  - **Admin notification:** subject reads "New inquiry: Axel Nova × TRLabs — {name}" or "Partnership application — {name}".
  - **Auto-reply:** the partner version names the joint team and the partner's founder. The application version never promises a quote.
- `/admin/inquiries` gains "Partner enquiry" and "Partnership" origin filters. Partner leads show "Partner · TRLabs".

## API

```
GET /v1/collaborations         Active partners, sort_order → id. Card fields + projects_count (public only)
GET /v1/collaborations/{slug}  Full profile + projects[] (public only; the ProjectResource shape
                               + collaboration {mode, role_note}). Inactive / deleted / unknown → 404
GET /v1/projects/{slug}        Now also partners[] {slug, name, symbol_url, mode} (public credits only).
                               The listing endpoint does NOT carry partners
```

## Pages and co-branding

- **`/collaborations`** (`pages/public/collaborations/index.vue`) has four parts:
  1. hero
  2. one co-branded card per active partner, linking to `/collaborations/{slug}`
  3. `CollabModes` (co-build / subcontract / referral)
  4. "Partner with us" (`CollabInquiryForm variant="collaboration"`) plus a note pointing referrers to `/partners`
- **`/collaborations/trlabs`** (`pages/public/collaborations/trlabs.vue`) is **hand-built**, not templated. Its sections, in order:
  1. hero: the named `CollabLockup` cards as the `h1` ("Axel Nova × TRLabs", one ×), with a GSAP entrance. The tiles slide in and meet at the ×; each grows into its card (surface clipped out of the tile, logo gliding up); then the names and copy. A server-rendered pre-paint script sets `html[data-collab-intro]` to hold the hero hidden until the entrance takes over (a 4s CSS failsafe reveals it regardless; reduced motion is never held). It stops the finished SSR hero flashing before it collapses to tiles
  2. Meet TRLabs + at-a-glance facts
  3. founders
  4. how we work together
  5. what each studio brings
  6. shared work (empty state today)
  7. "Start a project together" (`CollabInquiryForm variant="partner"`)
  - Facts, story, services and projects come from the API. Layout and section copy (including "What each studio brings") live in the page.
- **Co-branding is token-only.** The page (and each partner card or credit chip) wears the scope class `collab-trlabs`, which re-points `--color-accent*`, `--grad-text-accent`, `--grad-cta` and `--shadow-glow` at the `--collab-trlabs-*` tokens. Inside the scope:
  - eyebrows and `.text-gradient` run Axel Nova blue → violet → TRLabs orange;
  - the solid accent is TRLabs rust in light mode and their orange in dark mode (the rust fails contrast on navy).
  - Components never name partner colours. See [UI-STANDARDS.md § Partner co-branding](../frontend/UI-STANDARDS.md#partner-co-branding).
- **Logo plates:** every partner mark sits on `.collab-logo-plate`, a light tile in both themes, so a dark-ink logo stays legible in dark mode.
- **Caching:** `/collaborations` and `/collaborations/**` are `swr(300)`. There is no purge observer, because partner data only changes through a migration, which ships with a deploy.
- **Never create `frontend/public/collaborations/`.** A static folder named like a page route makes the server answer `/collaborations` with a 301 to `/collaborations/`. Partner assets live in `public/logos/`. `TrlabsSeedTest` guards this.
- **Navigation:** header link **"Collabs"**, placed after Projects and kept apart from "Partners" (the referral programme). It is also in the homepage hero pill's mirrored `navLinks` (`HeroEpoch.vue`), the mobile menu and the footer's Explore column, plus the cross-link on `/partners`.
  - With nine links, the header shows them only from `lg` (1024px), with `gap-5` until `xl`. Below that, the menu button takes over.
  - Measured: eight links already overflowed the bar by 47px at 768px. At 1024px there are now 52px of slack each side.
  - The hero pill's link list uses the same `lg` breakpoint.

## Common changes

**Link a project TR Labs worked on** (new migration; the project must already exist in `projects`):

```php
use App\Models\CollaborationPartner;
use App\Models\Project;

public function up(): void
{
    $trlabs = CollaborationPartner::where('slug', 'trlabs')->firstOrFail();
    $project = Project::where('slug', 'kenangan-majlis')->firstOrFail();

    // co_build credits publicly by default; subcontract / referral stay private
    // unless you pass creditPublic: true (only with the client's OK).
    $trlabs->linkProject($project, 'co_build', 'TRLabs: design + mobile app · Axel Nova: backend + admin');
}
```

**Edit TR Labs' profile:** a new migration that updates the row by slug
(`DB::table('collaboration_partners')->where('slug', 'trlabs')->update([...])`).
Do not edit the seed migration; it has already run in production.

**Add a second partner:** this is the trigger to build what v1 deferred:

- the admin CRUD (partners + project links with the public switch);
- a generic `/collaborations/[slug]` template;
- the partner's `--collab-{slug}-*` tokens and `.collab-{slug}` scope in `main.css`;
- a sitemap source for the dynamic slugs.

Until then, the index card for a new partner would link to a page that does not exist.

## Tests

- `tests/Feature/Collaborations/PublicCollaborationsTest.php`: listing and ordering, the three-way visibility rule, credit defaults, mode validation, the project-detail credit.
- `tests/Feature/Collaborations/TrlabsSeedTest.php`: the seed is live with its logo paths; the logo files exist and `public/collaborations/` does not. Skipped where `frontend/` isn't beside `backend/`, as in the dev container; it runs in CI.
- `tests/Feature/Inquiries/PartnerInquiryIntakeTest.php`: both new origins, partner validation, portfolio folding, emails, the admin list.
