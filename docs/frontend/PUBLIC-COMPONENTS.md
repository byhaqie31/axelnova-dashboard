# Public components

Components scoped to the public landing site only.

Rules:
- Used in 1 portal only — if used in 2+, move to `components/shared/`
- Genuine domain primitives live in `components/shared/primitives/`
- We don't wrap @nuxt/ui components as atoms — the library is our primitive layer
- Extract a shared component when used in 3+ places (rule of three)

## Components

### `HeroEpoch`
The landing hero (`pages/public/index.vue`). A `rounded-[48px]` video card (`bg-elevated` + hairline + `--shadow-lg`) with autoplayed muted background footage, eyebrow badge, Outfit (`.font-display`) headline, subhead, and a `.btn-pill-primary` CTA → `/quote`. A floating `.glass-nav` quick-links rail sits at the card's bottom edge, and a seamless pure-CSS logo marquee (stack logos, `.pill-chip` cards, pause-on-hover, edge mask) runs below.

- Runs its **own** GSAP entrance timeline (badge → headline SplitText → subhead → CTA → floating nav), font-gated + 3.5s safety timeout, full no-op under reduced motion. The page keeps `useScrollReveal('.reveal')` for the sections below.
- Forces `video.muted = true` in `onMounted` (Vue can drop the prop, breaking autoplay).
- **Pre-ship:** self-host the placeholder CloudFront video to R2 and the svgl.app logos to `/public/logos/`, then swap the URLs.

### `ReferralBand`
Partner-program shortcut band on the landing page (`pages/public/index.vue`), placed
right above `TestimonialWall`. Full-bleed `border-y` section with a soft
`--color-accent-soft` radial wash — deliberately quieter than the blue closing band,
whose "Have a project in mind?" pitch it inverts ("No project of your own? Refer one
instead."). Left column: badge, headline, CTAs → `/partners/refer` (accent) and
`/partners` (ghost), plus a one-line commission teaser (5% → 15%, RM150 flat for
Starter). Right column: the program's three steps as a connected vertical stepper.
Static content — commission copy must stay in sync with `pages/public/partners/index.vue`.

### `FeaturedMockups` / `FeaturedMockupCard` / `MockupPreviewModal`
The "Featured mockups" section on the landing page (`pages/public/index.vue`) —
live client prototypes from the registry at `https://axelnova.my/projects/registry.json`
(CORS-open, fetched client-side; filtering/sorting/offline-fallback live in
`composables/useMockupRegistry.ts`, shared with the admin dashboard section).

- **`FeaturedMockups`** — the snap carousel shell (same arrows/edge-fade pattern as
  `FeaturedProjectsCarousel`) with loading skeletons; owns the `previewing` state and
  mounts the modal.
- **`FeaturedMockupCard`** — browser-frame card with an mShots live screenshot
  (retry-on-placeholder, tinted fallback tile from the registry `tint {h,c}` →
  `hsl(h, c*400%, 55%)`). Whole card opens the preview popup; the hover "Visit live"
  pill skips straight to the site. Public status labels: `draft` renders as
  **Concept** (internal lifecycle words stay off the marketing site).
- **`MockupPreviewModal`** — Teleported full-screen browser-window overlay with a live
  `<iframe>` of the mockup (axelnova.my pages send no `X-Frame-Options`). Esc/backdrop
  close, scroll lock, loading spinner, 15s timeout → "open live site" fallback panel.
- `SectionHeader` gained an optional `action.target: '_blank'` for the external
  "View all" → `https://axelnova.my/` (the root showcase; the admin dashboard's
  section keeps the `/projects/` listing).
