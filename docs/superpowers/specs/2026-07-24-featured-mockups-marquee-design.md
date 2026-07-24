# Featured Mockups — Dual-Row Counter-Flow Marquee

**Date:** 2026-07-24
**Scope:** Frontend only — homepage "Featured mockups" section.
**Branch:** `feat/homepage-referral-band` (single-branch workflow).

## Goal

Replace the manual snap-scroll carousel in
`frontend/app/components/public/FeaturedMockups.vue` with two auto-flowing
horizontal marquee rows moving in opposite directions ("counter-flow"), using
smaller, denser cards. The registry now holds more mockups than the old
6-card carousel was designed for. The `SectionHeader` in
`frontend/app/pages/public/index.vue` (eyebrow / title / subtitle / "View all"
action) is untouched.

## Decisions (confirmed with owner)

| Decision | Choice |
|---|---|
| Item count | All public mockups — `useMockupRegistry(Infinity)` |
| Card content | Browser chrome + live screenshot + name + type line only |
| Interaction | Row pauses on hover; click opens `MockupPreviewModal`; "Visit live" hover shortcut stays |
| Animation driver | GSAP ticker (owner picked over pure CSS keyframes) |

## Design

### Data (`FeaturedMockups.vue`)

- `useMockupRegistry(Infinity)` — all public mockups, registry-sorted by
  `updatedAt`. Fallback snapshot (6 items) still applies.
- Split alternately: even indices → row 1, odd indices → row 2. Rows stay
  balanced as the registry grows.

### Rows

- Two stacked marquee rows with a small vertical gap. Row 1 drifts **left**,
  row 2 drifts **right**.
- Each row renders its card list **twice** (seamless wrap). The duplicate set
  is `aria-hidden="true"` + `inert` so assistive tech and keyboard focus meet
  each card exactly once.
- GSAP drive: one `gsap.ticker` callback per component advances each row's
  `x` by `speed * delta`, wrapping with `gsap.utils.wrap(-half, 0)` where
  `half` is the measured width of one card set. Direction is the sign of the
  speed. Speed constant tuned so a full loop takes roughly 30–45 s regardless
  of item count (px/s constant, not duration constant).
- Pause on hover: `pointerenter` on a row tweens that row's speed factor to
  0 (~0.4 s ease), `pointerleave` tweens it back to 1 — GSAP's win over the
  hard CSS `animation-play-state` stop. Touch devices: rows keep flowing;
  tap opens the modal.
- Edge fades: CSS `mask-image: linear-gradient(...)` on the section wrapper
  (both sides), replacing the current `v-show` gradient overlays.
- Set-width measurement re-runs on `ResizeObserver` and after
  `document.fonts.ready` (same trick the current component uses).
- Deleted: snap classes, arrow buttons, `atStart`/`atEnd`/`overflowing`
  scroll-state tracking, `go()`/`step()`.

### Reduced motion

`useMotion().reduced === true` → no ticker, duplicates not rendered, rows are
plain `overflow-x-auto` scrollable strips. Content never depends on JS to be
visible or reachable (repo scroll-reveal rule).

### Compact card (`FeaturedMockupCard.vue`)

- New `compact?: boolean` prop — same component, all mShots retry logic
  reused.
- Compact geometry: fixed card width `w-[240px] sm:w-[280px]`, browser-chrome
  bar slightly shorter (`h-7`), traffic-light dots and URL pill scaled down,
  same `aspect-3/2` viewport.
- Compact meta: name (`text-[14px] font-semibold`, one line) + type
  (`text-[12px]`, tertiary color) in a `p-3.5` footer. Status badge, client
  line, summary, and tag pills are not rendered in compact mode.
- Hover: keep `translateY(-4px)` lift + screenshot zoom + "Visit live" pill
  (pill scales down). Card hover also triggers the row pause via the row's
  `pointerenter` (no extra wiring).
- Default (non-compact) rendering unchanged — `MockupPreviewModal` grid or
  any other consumer is unaffected.

### Loading state

Skeleton shimmer tiles at compact card size, one static row of ~5 per row
(no animation of position while loading).

### SSR / hydration

Registry loads in `onMounted` (unchanged), so SSR renders skeletons only —
no marquee mismatch. Ticker starts after mockups render and set width is
measured (`nextTick` + measurement guard). All colors via existing CSS
tokens; both light and dark verified.

## Out of scope

- `SectionHeader` content or layout.
- `MockupPreviewModal`, `useMockupRegistry` internals (only the call-site
  limit changes).
- Admin mockups page (`pages/admin/mockups.vue`).
- Backend.

## Testing

- `npx vue-tsc` type check + ESLint (CI gates).
- Manual: light/dark, reduced-motion (rows scrollable, no duplicates), hover
  pause/resume, modal open, "Visit live" click doesn't trigger modal, mobile
  width, registry-fetch failure (fallback 6 render 3+3).
