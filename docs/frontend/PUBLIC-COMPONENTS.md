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
