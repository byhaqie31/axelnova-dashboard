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

### `FeaturedMockups` / `MockupMarqueeRow` / `FeaturedMockupCard` / `MockupPreviewModal`
The "Featured mockups" section on the landing page (`pages/public/index.vue`) —
live client prototypes from the registry at `https://axelnova.my/projects/registry.json`
(CORS-open, fetched client-side; filtering/sorting/offline-fallback live in
`composables/useMockupRegistry.ts`, shared with the admin dashboard section).

- **`FeaturedMockups`** — dual-row counter-flow marquee shell: fetches ALL public
  mockups (`useMockupRegistry(Infinity)`), splits them alternately into two rows
  (even indices → top row drifting left, odd → bottom row drifting right), applies
  the edge-fade `mask-image`, renders compact-size loading skeletons, and owns the
  `previewing` state + modal mount. It also owns the shared pause: each row reports
  its own hold via `@hold`, the shell ORs them into one `paused` prop, so holding
  either row stops **both**. The section carries `id="mockups"` (in
  `pages/public/index.vue`) — the hero nav's "Mockups" link jumps here.
- **`MockupMarqueeRow`** — one marquee row: renders its card set plus enough
  `aria-hidden` + `inert` duplicate sets to cover the container (`copies` derived
  from measurement, capped at 6 — one duplicate alone gaps when a set is narrower
  than the row) and drifts the track with a `gsap.ticker` callback at a constant
  px/s, wrapping into `[-setWidth, 0)`. Mouse hover or keyboard focus marks the row
  held (touch keeps flowing); the resulting `paused` prop eases speed to 0 and back.
  `prefers-reduced-motion` → plain scrollable strip, no duplicates, no ticker.
- **`FeaturedMockupCard`** — browser-frame card with an mShots live screenshot
  (retry-on-placeholder, tinted fallback tile from the registry `tint {h,c}` →
  `hsl(h, c*400%, 55%)`). Whole card opens the preview popup; the hover "Visit live"
  pill skips straight to the site. `compact` prop (used by the marquee) shrinks the
  chrome to `h-7` and trims meta to name + type — status badge, client, summary and
  tags render only in the full-size variant, where `draft` shows as **Concept**
  (internal lifecycle words stay off the marketing site).
- **`MockupPreviewModal`** — Teleported full-screen browser-window overlay with a live
  `<iframe>` of the mockup (axelnova.my pages send no `X-Frame-Options`). Esc/backdrop
  close, scroll lock, loading spinner, 15s timeout → "open live site" fallback panel.
- `SectionHeader` gained an optional `action.target: '_blank'` for the external
  "View all" → `https://axelnova.my/` (the root showcase; the admin dashboard's
  section keeps the `/projects/` listing).
