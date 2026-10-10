# Motion System

Framer-grade motion for axelnovaventures.com. Every value below was tuned in the
"Axel Nova Landing" prototype — **do not re-tune ad hoc**. This doc is the single
source of truth for animation; [UI-STANDARDS.md](UI-STANDARDS.md) §8 defers here.

Brand feel: modern luxury, minimalist, weighted. Premium hotel lobby, not a video
game. Never bouncy (the single elastic ease is the magnetic-button release only),
never playful.

## Tokens — `app/utils/motion.ts`

Every animation must import from `MOTION`; no magic numbers in components.

| Token | Value | Use |
|---|---|---|
| `dur.fast` | 0.3s | Dashboard register, micro-interactions |
| `dur.base` | 0.6s | Standard entrances (hero badge) |
| `dur.slow` | 0.9s | Scroll reveals, hero subhead/CTAs |
| `dur.hero` | 1.2s | Signature moments |
| `ease.out` | `power3.out` | Default for everything |
| `ease.hero` | `expo.out` | Hero / signature moments only |
| `ease.inout` | `power2.inOut` | Page transitions |
| `ease.spring` | `elastic.out(1, 0.4)` | Magnetic-button release **only** |
| `ease.settle` | `power1.out` | Counters decelerating into a number |
| `stagger.tight` | 0.08 | Dashboard tiles |
| `stagger.base` | 0.1 | Cards, SplitText words |
| `stagger.loose` | 0.14 | Sparse layouts |
| `reveal.y` | 52px | Scroll-reveal travel |
| `reveal.start` | `top 85%` | Scroll-reveal trigger point |

Surfaces: the **marketing site** uses `base`–`hero`; **admin/portal dashboards**
use `fast`–0.5s (daily-use tools — counters shorten to ~0.9s, entrances to ~0.4s).
No parallax, no magnetic, no SplitText on dashboards.

## Plumbing

- `app/plugins/gsap.client.ts` registers ScrollTrigger + SplitText (free since
  GSAP 3.13), boots **Lenis** (`lerp: 0.1`) synced through GSAP's ticker with
  `lagSmoothing(0)` (required — without it Lenis stutters), and provides
  `$gsap / $ScrollTrigger / $SplitText / $lenis`.
- On `page:finish` it resets scroll **through Lenis** (`scrollTo(0, { immediate:
  true })` — native resets desync Lenis's internal position) and refreshes
  ScrollTrigger.
- Same-page anchor clicks (`a[href^="#"]`) are routed through
  `lenis.scrollTo(target, { duration: 1.1 })` — native anchor jumps bypass Lenis.
- `main.css` sets `html.lenis { scroll-behavior: auto }` — global
  `scroll-behavior: smooth` fights Lenis and breaks anchor jumps.
- **ScrollTriggers that scrub against Lenis use `scrub: true`** (hero zoom,
  parallax). Lenis already eases the scroll position, so following it 1:1
  inherits that smoothing; a numeric `scrub` restarts a second ease tween every
  frame on top and, measured, adds nothing visible — only per-frame work.
- Lenis is disabled entirely under `prefers-reduced-motion`. On touch devices
  Lenis 1.x leaves native momentum scrolling alone; verify on iOS before
  configuring `syncTouch`.
- `useMotion()` is the typed accessor for `$gsap` etc. plus the shared `reduced`
  flag. **Capture it at setup** — Nuxt context is unavailable inside event /
  `nextTick` / timer callbacks.

## Composables

All are client-guarded, kill their tweens/triggers on unmount, and no-op
(content fully visible) under reduced motion.

| Composable | What it does |
|---|---|
| `useScrollReveal(selector, options?)` | The standard `.reveal` API (unchanged signature). y: 52 → 0, 0.9s, `power3.out`, `top 85%`, once; `clearProps` on complete. Elements are grouped with `ScrollTrigger.batch` (`interval: 0.1`): within a batch, elements the viewport has already scrolled past just become visible (`clearProps`), and the ones on screen cascade 0.06s apart, capped at 0.3s total so a full grid of cards compresses instead of queueing. **Never delay by page-wide index** — the old `delay: i * 0.06` held the 30th section invisible ~2s after it was on screen (measured 2.5s to visible on `/partners`, 1.1s after the fix), which read as the page being stuck. |
| `useReveal(target, opts?)` | Ref- or selector-based reveal with group `stagger` (single trigger on the first element). Same token values. |
| `useSplitTextReveal(target, opts?)` | Signature headline: SplitText `type: 'words', mask: 'words'`, yPercent 110 → 0, 1.1s, `expo.out`, stagger 0.1. Returns `{ build }` — call inside `onMounted` to get the timeline for sequencing. Reverts the split after the entrance (resize-safe). |
| `useCountUp(target, end, opts?)` | Counts 0 → end on first view (`top 88%`, once), 1.8s (dashboard ~0.9s), `power1.out`, snapped to integers, written to `textContent`. Put `tabular-nums` on the element. SSR renders the real value; JS zeroes it post-hydration. Named `useCountUp` (not `useCounter`) to avoid colliding with VueUse's auto-imported `useCounter`. |
| `useParallaxImage(target)` | yPercent −5 → 5 scrubbed drift for media in an `overflow: hidden` mask sized 114% height / `top: -7%`. Desktop `pointer: fine` only. (No call sites yet — project cards have no imagery.) Named `useParallaxImage` (not `useParallax`) to avoid colliding with VueUse's auto-imported `useParallax`. |
| `useMagnetic(btnRef)` | Desktop-only magnetic CTA: button 0.3× cursor offset, inner `.magnetic-label` span 0.12×, `quickTo` 0.4s; release springs back 0.8s `elastic.out(1, 0.4)`. Adds `.is-magnetic` (drops the CSS transform transition). Wrap button contents in `<span class="magnetic-label">`. |

## Choreography (as built)

- **Hero** (`pages/public/index.vue`): badge 0.6s → headline SplitText 1.1s at
  `-=0.3` → subhead 0.9s at `-=0.55` → CTAs 0.9s at `-=0.7`. A ~3.5s safety
  timeout force-finishes the timeline (throttled/background tabs never run rAF).
  The gradient headline line gets per-word background mapping during the split —
  `background-clip: text` on an ancestor stops painting inside transformed
  descendants.
- **No preloader/curtain.** It delays content paint; LCP wins.
- **Stats row**: cells cascade (`useReveal` group, stagger 0.1), values count via
  `useCounter`.
- **SectionHeader**: 38×1.5px accent rule draws in `scaleX 0 → 1`, 0.9s, 0.2s
  delay, at `top 88%`.
- **Filter tabs**: one absolutely-positioned pill tweens x/width 0.45s to the
  active button; re-synced on resize **and `document.fonts.ready`** (font swap
  changes widths). Grid swap: out 0.22s / stagger 0.03 / `power2.in` → swap →
  in 0.5s / stagger 0.06 / `power3.out` + `clearProps`. Two prototype-proven
  rules: **kill the in-flight timeline on rapid clicks** (an `isAnimating`
  early-return drops inputs and strands cards invisible), and
  **`immediateRender: false` on the incoming `fromTo`** (otherwise visible cards
  flash out at build time).
- **Nav** (`layouts/public.vue`): tint + 48 → 44px shrink past 20px; hides
  (`translateY(-110%)`, 0.5s) on scroll-down past 160px with a 6px accumulator,
  reveals on any 4px scroll-up; never hides while the mobile drawer is open.
- **Page transitions** (`app.vue`): GSAP JS hooks, `out-in` — leave 0.3s
  `power2.in` (opacity, y −20), enter 0.45s `power3.out` (opacity, y 24 → 0).
- **Nav flight** (`composables/useNavFlight.ts`, run by `layouts/public.vue`,
  fed by `HeroEpoch`): on `/` the nav is the hero's pill at the card's bottom,
  everywhere else the header at the top — so crossing between them, the
  header's bar travels between the two spots while the page swaps underneath.
  **Leaving `/`** with the pill on screen (router `afterEach`, so a cancelled
  navigation never strands it): the pill hides and the bar launches from its
  rect up to the top, `dur.slow` / `ease.inout`, content fading in
  (`dur.base`, from `dur.fast`). **Arriving at `/`** in settled mode from a
  page whose header was on screen: the bar glides down to the pill's resting
  spot (dock math at scroll 0), content fading out, then swaps to the pill in
  one frame and the pill's content fades up (`dur.fast`); the dock trigger is
  only created after the landing. Skipped on back/forward (a restored scroll
  position means the pill isn't there), on hash links, when the header was
  hidden by scroll, on first-visit intros and under reduced motion.
  `.nav-header-instant` drops the header's 0.5s slide for the swap frames.
  The bar tweens **`width`** as well as transform (the two bars differ by up
  to ~100px): scaling would squash its text, and it's one fixed element —
  the same exception the hero pill's own width scrub already takes.
- **ProjectCard**: CSS-only hover — −4px lift + glow (existing) + diagonal sheen
  sweep (`--sheen-color`, theme-aware).
- **Admin dashboard**: tiles stagger in on mount (y 16, 0.4s, stagger 0.08, once
  per navigation); metric counters ~0.9s when data arrives.

## Rules for new animations

1. **Use the tokens.** If a value isn't in `MOTION`, justify adding it there —
   never inline.
2. **Transform and opacity only** (counters animate a JS proxy). `will-change`
   sparingly, removed after. Never animate border/underline properties — draw
   underlines with a `scaleX` `::after` (`.link-underline`).
3. **Reveals run once.** Never scrub-reverse a reveal.
4. **`clearProps: 'opacity,transform'` after entrances** — leftover transforms
   break `position: sticky`/`fixed` descendants and hover transforms.
5. **Initial hidden states are set by GSAP only** — never `opacity-0` in
   CSS/classes. JS-off and SSR must paint full content (hero text stays in the
   SSR payload — no LCP regression).
6. **Reduced motion is a no-op, not a degraded copy**: entrances skipped, content
   instantly visible, Lenis off; essential state transitions kept.
7. **Cleanup**: every composable kills its tweens/ScrollTriggers on unmount and
   reverts SplitText — this is an SPA; leaked triggers corrupt scroll positions
   after navigation.
8. **One signature moment per page** (hero SplitText). Everything else supports
   quietly. If an animation doesn't add meaning, cut it.
9. **Mobile**: parallax + magnetic disabled on touch; reveals stay simple.
10. **No CSS transition on a property GSAP animates.** The transition re-eases
    every per-frame value GSAP writes: `transition-all` on the project card
    stretched its 0.9s reveal to ~1.05s to full opacity and made the initial
    hide a visible fade-out at mount. `useScrollReveal` now sets
    `transition: none` inline while it owns an element, but hover transitions on
    reveal targets must still name their properties
    (`transition-[transform,box-shadow]`), never `transition-all`.
