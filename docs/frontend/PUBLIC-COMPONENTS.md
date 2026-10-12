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

- Runs its **own** GSAP entrance timeline (badge → headline SplitText → subhead → CTA → floating nav), started by the loader's `reveal` event + a safety timeout, full no-op under reduced motion. The page keeps `useScrollReveal('.reveal')` for the sections below.
- **The whole intro plays once per session** (sessionStorage `axn-hero-intro-seen`): loader, fullscreen boot pose, pinned zoom-out and entrance. Every later visit to `/` — header "Home" link, back button, same-session reload — renders the **settled home view** instead: the designed card (the natural CSS layout), no pin, no zoom, no entrance; only the pill → header dock handoff runs (`dockSt` with a `0` pin offset). A reload can't know about sessionStorage during SSR, so every `.hero-boot` rule is gated on `html:not([data-intro-seen])` — the pre-paint script stamps that attribute and the fullscreen pose never paints.
- **The fullscreen boot pose is defined twice and must stay in sync:** as `.from()` values on the pinned zoom timeline, and mirrored in the `.hero-boot` scoped CSS so SSR paints it before GSAP hydrates. Change one, change the other. Current pose: eyebrow + headline corner-scaled, description/CTA/navbar faded out, and the whole `.hero-content` block dropped `12svh` / `22svh` (md) toward the card's optical centre — only two elements are visible in this pose, so top-aligning it left a dead band.
- **The pin is one viewport, and lengthening it does not create a "hold on the full page".** While pinned, the pin-spacer occupies the layout below the fixed hero, so the marquee and stats are pushed hundreds of px off-screen — a longer pin holds a card floating over white space, not the composed view. Measured at a 2-viewport pin: 662px of empty space between the card and the marquee. The composed view only exists once the pin releases.
- **There is no scroll hold on arrival, and don't add one.** A timed `lenis.stop()` dwell was built here and removed: freezing the page, even for 3s and even once, reads as a broken site rather than a considered pause. That was the reported symptom from real use, not a theory.
- **Mobile smoothness levers** (the pinned hero is the most expensive thing on the page for phones): `ScrollTrigger.config({ ignoreMobileResize: true })` in `plugins/gsap.client.ts` stops URL-bar show/hide from refreshing the pin mid-scroll; `anticipatePin: 1` prepares the switch to fixed positioning a tick early; the hero video is `preload="metadata"` so phones don't buffer the whole clip against the pin. The remaining lever is Lenis `syncTouch` — currently **off**, so touch scrolling is native while ScrollTrigger runs off Lenis's rAF loop. Turning it on makes pinning consistent but replaces native momentum site-wide, so treat it as a deliberate feel decision, not a perf tweak.
- **Crossing to/from other pages, the header travels to/from the pill's spot** (the nav flight — `useNavFlight`, run by `layouts/public.vue`; timings and skip rules in [MOTION.md](MOTION.md)). `HeroEpoch` reports the pill's rect on leave and the resting spot on a settled arrival; keep the landing-spot math in sync with the dock math (`padTopPx`, `CARD_END_PX`, `dockBottomPx`, `PILL_H_PX`).
- **The dock handoff needs no change when the pin length changes** — `dockSt.start` is computed from `zoomTl.scrollTrigger.end`, so it shifts automatically.
- **Boot-pose offsets are transform-only, never layout.** `.hero-shell` is the pin trigger; changing its height corrupts the pin spacer and the `.hero-marquee-wrap` negative-margin math that tucks the marquee under the settled card.
- Forces `video.muted = true` in `onMounted` (Vue can drop the prop, breaking autoplay).
- **Pre-ship:** self-host the placeholder CloudFront video to R2 and the svgl.app logos to `/public/logos/`, then swap the URLs.

### `HeroLoader`
The homepage intro overlay — `BrandMark variant="stacked"` (oversized mark above the wordmark) over an animated `.loader-grid` backdrop, with the iridescent progress bar and pulsing "Loading" label below. Mounted by `HeroEpoch`.

The backdrop is a **child** `<span class="loader-grid">`, never a class on the root: `main.css` is unlayered, so a decorative wrapper class setting `position: relative` overrides the root's Tailwind `fixed`, collapsing the overlay into normal flow and exposing the page behind it (hard rule 8 in [UI-STANDARDS.md](UI-STANDARDS.md)). When changing this overlay, assert its bounding box still covers the viewport — `display !== none` passes even when the layout has collapsed. Purely visual — the parent owns readiness and flips `done`; this component completes the bar, fades out, and emits `reveal` as the fade starts so the hero entrance can begin underneath.

**It is rendered during SSR**, so it covers the page from the first paint. `showLoader` therefore starts `true`, and the cases that must never see it are hidden *before* paint rather than unmounted after hydration:

- The inline script in `nuxt.config.ts` (`app.head.script`) stamps `data-intro-seen` on `<html>` for repeat visits this session and for `prefers-reduced-motion`; `main.css` hides `.hero-loader` on that attribute.
- `HeroEpoch` stamps the same attribute once the overlay is **fully gone** (the loader's `hidden` event) and on the repeat-visit branch — the pre-paint script only sees sessionStorage as it was at page load, so without this a client-side nav back to `/` would flash the overlay for a frame. **Timing is load-bearing:** the attribute is what hides the loader, so stamping it when the intro *starts* (e.g. next to the `sessionStorage.setItem`) kills the overlay a frame after hydration and the intro never plays.
- A `<noscript>` rule hides it too, so a JS failure can't leave the page covered.

Getting this wrong is a visible FOUC — see hard rule 9 in [UI-STANDARDS.md](UI-STANDARDS.md).

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

### `BlogCard`
One post in the `/blog` grid, the article's "More from the blog" row and the home page's `BlogLatest`. Cover is a URL (a soft `newspaper` placeholder when absent), meta line (category · date · reading time), title, 3-line excerpt. Whole card is a `NuxtLink`.

### `BlogLatest`
"From the blog" section on the landing page (`pages/public/index.vue`), right below the client previews (`#mockups`). Reads page 1 of `GET /v1/blog/posts` and keeps the newest three (`transform` trims the rest out of the SSR payload), rendered as `BlogCard`s with a "View all" → `/blog` action. Three-up from `lg`; at `md` it's two-up and the third card is hidden so it never sits alone on a row. **Renders nothing** when no post is published or the feed fails — same contract as `TestimonialWall`. Rides the home page's `swr: 300`; publishing purges that cache, so a new post reaches the home page on the next visit.

### `BlogToc`
"On this page" — anchors from the backend `toc`. An `IntersectionObserver` highlights the heading nearest the top (`.is-active`, styled in `main.css` under `.blog-toc`); degrades to a plain list without it.

### `BlogArticle`
The one article layout, shared by `pages/public/blog/[slug].vue` and the admin editor's preview (`preview` prop hides share + related and makes the CTA inert). Renders backend-sanitised `body_html` into `.blog-prose` via `v-html` — never raw Markdown or user HTML. Header (format eyebrow, title, lede, byline) and cover span the full width; below them a `[220px pane][≤78ch text]` grid puts a sticky left pane (`BlogToc`, from 2 sections up) beside the text; the text column closes with a rule + the share row. The closing CTA card then spans both tracks (copy left, button right from `md`; stacked below), followed by related posts — so the sticky pane stops at the share row. The pane is inline above the first section below `lg`. See [BLOG.md](../global/BLOG.md).

### `CollabLockup` / `CollabPartnerCard` / `CollabsLatest` / `CollabModes` / `CollabInquiryForm`

The `/collaborations` building blocks (see [COLLABORATIONS.md](../global/COLLABORATIONS.md)). All three inherit the accent of the scope they sit in, so inside `.collab-trlabs` they take the co-brand ([UI-STANDARDS § Partner co-branding](UI-STANDARDS.md#partner-co-branding)).

- **`CollabLockup`:** "Axel Nova × {partner}", the two marks on matching `.collab-logo-plate` tiles with ONE `.text-gradient` × between them. Props: `partnerName`, `partnerSymbol` (the square `symbol_url`; falls back to an initial), `size` `md` | `lg`, `named`, `as` (`div` | `h1` | `h2`).
  - **Bare** (default): one picture (`role="img"` + label). Not currently used on a page.
  - **`named`:** each tile sits in an equal-size, theme-aware card with the studio name under it ("Axel Nova" / the partner). Wherever the pair appears it IS the heading, so no text title repeats the pair and its ×:
    - `lg` + `as="h1"`: the partner-page hero.
    - `md` + `as="h2"`: the `/collaborations` card. Compact cards, sized against the viewport so two cards + the × fit inside the padded card on a 320px phone.
  - The heading text reads "Axel Nova × TRLabs". The × carries its own spaces because Vue condenses the whitespace between tags, which would give "Nova×TRLabs". Names pin their own letter-spacing against main.css's unlayered `h1 {}` rule.
  - **Hover** (named, pointer devices, motion allowed; CSS only): the card lifts 6px on `--shadow-lg`, its mark scales to 1.06, and the × makes a quarter turn while either card is hovered (`:has()`).
  - **Entrance hooks:** `data-lockup="left|x|right"`, `data-lockup-plate`, `data-lockup-chrome` (the card surface, a separate layer) and `data-lockup-name`. An entrance must not resize the card. Grow the chrome with `clip-path` and move the plate with a transform, so nothing re-lays out. Suspend the hover transitions inline while animating (MOTION rule 10).
- **`CollabPartnerCard`:** one partner as a full-width co-branded link card: the named lockup as its heading, then the eyebrow, summary, service chips and "See the collaboration →".
  - Props: `partner`, `headingLevel` (`h2` on `/collaborations`, `h3` under a section's h2).
  - It wears `collab-{slug}`. The parent puts `reveal` on a wrapper, never on the card, because GSAP's leftover inline transform would cancel the hover lift.
- **`CollabsLatest`:** the homepage section above the blog ("Built with trusted studios." + cards + "All collaborations"). It shares the `/collaborations` fetch key and renders nothing when no partner is active, like `BlogLatest`.
- **`CollabModes`:** the three ways of working (co-build / subcontract / referral) as three `reveal` cards. Shared copy, used by the index and the partner page.
- **`CollabInquiryForm`:** one form, two variants, both posting to `POST /v1/inquiries`.
  - `partner` (needs `partnerSlug` + `partnerName`): a client hiring the partner through Axel Nova, sent with `origin=partner` and `partner={slug}`. It collects name, email, phone, company, a project-type pill and a message.
  - `collaboration`: a studio applying, sent with `origin=collaboration`. It collects studio name, `portfolio_url`, a "what best describes you" pill (sent as `subject`) and a message.
  - Uses the shared `.contact-input`, the `website_url` honeypot and `useReferralAttribution().withRef`, like `/contact`.

### `ServicePackageCard`
One catalogue package (`GET /v1/services`, API shape) — the `/services` listing and every `/services/{slug}` page render this one card, so the two always show the same package: name, tagline, `PriceTag` (stacked), duration + revisions, features, and the CTA (`/quote?category=&package=` when the package has a `quote_key`, else a mailto). "Most popular" on `featured`.

- **Brief on the listing, full on the service page.** The listing passes `featureLimit` (5) + `moreTo` (`/services/{service}#{package-slug}`): the first 5 features in the admin's order, then "+N more features →". The service page passes neither and shows every feature. The order is set in the admin, so each package should lead with its strongest features.
- **The card's `id` is the package slug** — the anchor `moreTo` lands on. Arriving from the listing lands straight on it, with its section already visible (`utils/hashScroll.ts`; see [MOTION.md](MOTION.md#plumbing)).
- **Price sizes to the card, not the viewport** (`@container`): 30px from a 24rem card, 26px below (the service page's narrower 3-up, the listing below `xl`), so a range like "RM 10,000 – 16,000" stays on one line.
- Props: `pkg`, `headingLevel` (`h3` on the listing, `h4` under the service page's "Packages" h3), `featureLimit` (0 = all), `moreTo`, `convert` + `symbol` (the listing's currency switcher; amounts arrive in MYR).

### `SiteFooter`
The storefront footer, mounted once by `layouts/public.vue` (which passes its nav `links` array, so the **Explore** section always matches the header). Laid out after apple.com's footer:

- **Directory** — Explore · Services · Support · Legal. Explore is the header nav (`links` from `layouts/public.vue`). Below `md` each section is a 44px accordion row (chevron right, hairline between rows) that toggles independently — closing a row above the one just tapped would shift it out from under the finger. From `md` up the same markup shows four always-open columns (12px semibold heading, 12px links). The panel animates `grid-template-rows: 0fr → 1fr` (no height measuring); `visibility` keeps a collapsed row's links out of the tab order. Open rows reset on route change.
- **Every link is in the SSR markup at every width** — a collapsed row is hidden, not unrendered — so crawlers still reach every `/services/{slug}` page (see [SEO.md](SEO.md)). The Services section reads `GET /v1/services` (`key: 'footer-services'`) and falls back to a single "All services" link if the API is down.
- **Company identity** — from `lg` the **first column of the directory row**, in line with Explore / Services / Support / Legal (`lg:grid-cols-[1.5fr_4fr]`, name with the headings' 12px semibold + `mb-3`); below `lg` it drops under the directory (`order-last`). Three plain lines, no card: "Axel Nova Ventures" set like the section titles (14px → 12px from `md`, semibold, `--color-text`), then Registration No. / Kuala Lumpur, Malaysia in the copyright line's type (12px, `--color-text-secondary`). No tagline or availability badge.
- **Back to top** is no longer in the footer — it floats on every public page (`ScrollTopButton`, below).
- **Bottom bar** — copyright left, socials right (centred and stacked on phones; the `/admin` gear was removed in #64). The owner wants this bar kept as is; restyle the sections above it, not this.
- Honours `definePageMeta({ footerGap: false })` (drops the `mt-32` above it) for pages that end on their own band.

### `ScrollTopButton`
The floating **Back to top**, mounted once by `layouts/public.vue` (every public page). A 44px round button, bottom-right (16px in, 24px from `md`, plus the iPhone safe-area inset), `--color-bg-elevated` with `--shadow-md`, an `i-lucide-arrow-up` in `--color-text`. `aria-label` / `title` "Back to top" — no visible caption.

- **Shows only while scrolling up** past ~60% of the first screen — the moment a visitor is heading back — and hides as they scroll down or reach the top, like the header's reveal (8px up / 6px down accumulators so jitter doesn't flicker it). Hidden = faded, nudged down, slightly small, click-through and out of the tab order.
- **Progress ring** — an SVG circle round the button: a `--color-border` track and an aurora stroke (`--grad-aurora-*` stops) that fills clockwise from 12 o'clock as the page scrolls down and drains on the way up (`stroke-dashoffset` over the scrollable height, rAF-throttled).
- **Click** scrolls through Lenis when it owns scrolling (native smooth scroll would fight it), then focuses the header's brand link.
- `z-index: 30` — under the header (z-50) and the cookie banner (z-60). Reduced motion: fades only, no movement, instant jump.
