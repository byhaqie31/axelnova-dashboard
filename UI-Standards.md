# UI Standard — axelnova.tech

The design system reference for `axelnova-dashboard`. Apple-inspired premium minimalism, graphite + Apple Blue + iridescent accent moments. This document is the source of truth. Implementation lives in [`app/assets/css/main.css`](app/assets/css/main.css).

---

## 1. Design principles

1. **Clarity over decoration.** Whitespace, typography, and contrast carry the design — accents are sparse.
2. **Premium feel from the first frame.** No FOUC, no broken animations. If JS fails, content stays visible.
3. **Both modes are first-class.** Light and dark are designed together, not derived.
4. **Tokens, never raw hex.** Components consume CSS variables. New colors are added to `main.css` first.
5. **Motion expresses cause and effect.** Animations clarify hierarchy; nothing animates "just because".
6. **Accessibility is non-negotiable.** WCAG AA contrast, 44pt touch targets, reduced-motion support, visible focus rings.

---

## 2. Color tokens

All colors are semantic CSS variables defined in `:root` (light) and `.dark` (dark). Never hardcode hex in templates.

### Surfaces

| Token | Light | Dark | Use |
|---|---|---|---|
| `--color-bg` | `#FFFFFF` | `#000000` | Page background |
| `--color-bg-elevated` | `#FBFBFD` | `#161617` | Cards, raised panels |
| `--color-bg-secondary` | `#F5F5F7` | `#1D1D1F` | Section/banner background |
| `--color-bg-sunken` | `#EEEEF2` | `#0A0A0B` | Recessed inputs / wells |

### Text

| Token | Light | Dark | Use |
|---|---|---|---|
| `--color-text` | `#1D1D1F` | `#F5F5F7` | Body / heading |
| `--color-text-secondary` | `#6E6E73` | `#A1A1A6` | Subheadings, captions |
| `--color-text-tertiary` | `#86868B` | `#86868B` | Helper / disabled-ish |

### Borders

| Token | Light | Dark | Use |
|---|---|---|---|
| `--color-border` | `rgba(0,0,0,0.08)` | `rgba(255,255,255,0.10)` | Default hairlines |
| `--color-border-strong` | `rgba(0,0,0,0.14)` | `rgba(255,255,255,0.16)` | Buttons, hover borders |

### Brand & status

| Token | Light | Dark | Use |
|---|---|---|---|
| `--color-accent` | `#0071E3` (Apple Blue) | `#2997FF` | Primary CTA, links |
| `--color-accent-hover` | `#0077ED` | `#47A6FF` | Hover state |
| `--color-accent-soft` | `rgba(0,113,227,0.10)` | `rgba(41,151,255,0.14)` | Tinted backgrounds |
| `--color-success` | `#30D158` | (same) | Positive status |
| `--color-warning` | `#FF9F0A` | (same) | In-progress |
| `--color-danger` | `#FF3B30` | (same) | Destructive |

### Iridescent gradient stops

Used for premium moments only — hero text, top-of-nav hairline, brand dot. Never for body content.

| Token | Hex | Role |
|---|---|---|
| `--grad-aurora-indigo` | `#6366F1` | |
| `--grad-aurora-blue` | `#0071E3` | |
| `--grad-aurora-cyan` | `#06B6D4` | |
| `--grad-aurora-violet` | `#A855F7` | |
| `--grad-aurora-pink` | `#EC4899` | |

### Composite gradients

| Token | Use |
|---|---|
| `--grad-iridescent` | Brand dot, premium accents (135° violet → blue → cyan) |
| `--grad-aurora-line` | Animated 1px hairline at top of navbar/footer |
| `--grad-text-premium` | Subtle mono gradient on headlines |
| `--grad-text-accent` | Gradient text (used on hero headline highlight) |
| `--grad-cta` | Primary gradient pill button background |

### Nav theme tokens

These flip automatically with `:root` / `.dark`. Use for any sticky overlay surface.

- `--nav-bg-top` — header backdrop when at top of page
- `--nav-bg-scrolled` — header backdrop after scroll
- `--nav-mobile-bg` — mobile menu sheet

**Rule:** never bind these to `colorMode.value` in JS — drive them only through CSS variables to avoid hydration FOUC.

---

## 3. Typography

- **Family:** Inter (fallback: SF Pro Display, system sans). Loaded via `@nuxtjs/google-fonts` with `display: swap`.
- **Feature settings:** `cv11`, `ss01`. Body letter-spacing `-0.011em`.
- **Headings:** weight `600`, letter-spacing `-0.022em`, line-height `1.08`. `h1` tightens to `-0.045em`.

### Scale

| Use | Size | Tracking | Weight |
|---|---|---|---|
| Hero display | `clamp(52px, 9vw, 112px)` | `-0.05em` | 600 |
| Page H1 | `clamp(40px, 6vw, 72px)` | `-0.045em` | 600 |
| Section H2 | `2.25rem`–`3rem` | `-0.022em` | 600 |
| Card title | `20px` | tight | 600 |
| Body | `16–19px` | `-0.011em` | 400 |
| Caption / label | `12–13px` | `0` | 400–500 |
| Stat numeral | `2.5–3rem` | tight | 600, **tabular-nums** |

### Utility classes

- `.text-gradient` — accent gradient on text
- `.text-gradient-mono` — premium graphite gradient
- `.eyebrow` — uppercase, 12px, gradient accent
- `.label` — 12px, secondary color
- `.tabular-nums` — `font-variant-numeric: tabular-nums` for data

---

## 4. Spacing & layout

- **Container:** `max-w-7xl mx-auto px-6` for desktop content rows.
- **Section padding:** `py-32` (128px) standard; `py-20` for compact CTA banners.
- **Spacing rhythm:** 4 / 8 / 12 / 16 / 24 / 32 / 48 / 64 / 96 / 128.
- **Hero min-height:** `calc(100vh - 49px)` (viewport minus nav 48px + 1px aurora hairline).
- **Breakpoints:** Tailwind defaults — `sm` 640, `md` 768, `lg` 1024, `xl` 1280.

---

## 5. Elevation

| Token | Use |
|---|---|
| `--shadow-xs` | 1px subtle border-replacement |
| `--shadow-sm` | Resting cards |
| `--shadow-md` | Raised buttons on hover |
| `--shadow-lg` | Modals, sheets |
| `--shadow-glow` | Focus ring (4px accent) |
| `--shadow-card-hover` | Project card hover (16px elevation) |

Cards use `border + bg-elevated`. On hover, lift `translateY(-4px)` + apply `--shadow-card-hover`.

---

## 6. Buttons

All buttons are 44pt tall (Apple HIG touch target), pill-shaped (`border-radius: 980px`), `font-weight: 500`, `font-size: 14px`. `:active` scales to `0.97`.

| Class | Style |
|---|---|
| `.btn-pill-primary` | Solid `--color-text` on `--color-bg` (high contrast) |
| `.btn-pill-ghost` | Transparent + `--color-border-strong` |
| `.btn-pill-accent` | `--grad-cta` gradient + brand-tinted shadow + hover lift |

**Rule:** one primary CTA per screen. Secondary actions are ghost.

---

## 7. Components

Reusable components live in `app/components/shared/`.

### `SectionHeader`
- `eyebrow` (uppercase gradient)
- `title` (h2)
- `subtitle` (optional)
- `action` (link with chevron, gap grows on hover)

### `ProjectCard`
- 24px padding, 16px radius, `bg-elevated` + hairline border.
- 40×40 accent-tinted icon tile (top-left).
- Status pill with colored dot (Live / Soon / In progress).
- Hover: `translateY(-4px)` + `card-glow` radial wash + arrow CTA fades in.

### Filter pills
- 28px tall, 16px horizontal padding, hairline border.
- Active: solid `--color-text`, `--shadow-sm`.

---

## 8. Motion

- **Micro-interactions:** 150–300ms.
- **Page enter / scroll reveal:** 600–800ms with `power2.out` / `power3.out`.
- **Card hover:** 300ms ease.
- **Aurora drift:** 24s `ease-in-out` infinite alternate (background mesh).
- **Aurora line shift:** 14s ease-in-out infinite (navbar gradient hairline).
- **Page transition:** 250ms opacity + translateY (`<NuxtPage>` `out-in` mode).
- **Reduced motion:** all decorative animations short-circuit; content goes to final state immediately.

### Scroll reveal contract

- Add `class="reveal"` to elements that should fade in.
- Page must call `useScrollReveal('.reveal')` once.
- The composable handles `prefers-reduced-motion`, ScrollTrigger refresh, and unmount cleanup.
- **Do not add `opacity-0` Tailwind classes** — let GSAP set the initial state, so content stays visible if JS fails.

---

## 9. Accessibility

- Body text contrast ≥ 4.5:1 in both modes (verified with WebAIM contrast checker).
- All interactive elements have a visible focus ring via `--shadow-glow`.
- Touch targets ≥ 44×44pt.
- Icons-only buttons must have `aria-label`.
- Animations respect `prefers-reduced-motion: reduce`.
- Heading hierarchy is sequential — no skipped levels.
- Semantic HTML: `<nav>`, `<main>`, `<footer>`, `<section>`.

---

## 10. Hard rules (do not break)

1. **No `colorMode.value` in template `:style` for layout surfaces.** Use CSS vars; `.dark` is set pre-paint by `@nuxt/ui`.
2. **No hardcoded hex in `.vue` files.** Add a token to `main.css` first.
3. **No emojis as icons.** Use `<UIcon>` (Lucide via Iconify).
4. **No `opacity-0` static class on JS-revealed elements.** Use `gsap.set()` in `onMounted`.
5. **One primary CTA per screen.**
6. **Tailwind v4 canonical classes** — `tracking-tighter`, not `tracking-[-0.05em]`; `border-b-0!`, not `!border-b-0`.
7. **Iridescent gradients are sparse.** Hero headline accent, brand dot, top-of-nav hairline, CTA banner backdrop. That's it.

---

## 11. Adding a new page — checklist

- [ ] Use `<NuxtLayout>` default (gives navbar + footer).
- [ ] Top section either centered hero or full-bleed with section padding `py-32`.
- [ ] Call `useScrollReveal('.reveal')` once in `<script setup>`.
- [ ] All surfaces use `var(--color-bg-*)` / borders use `var(--color-border)`.
- [ ] Buttons use `.btn-pill-*` classes.
- [ ] Verified light + dark, mobile (375px) + desktop (1440px).
- [ ] No `opacity-0` on JS-animated elements.

---

## 12. Updating this doc

Whenever a new token, component, or motion rule is introduced:
1. Add it to `app/assets/css/main.css`.
2. Document it here in the matching section.
3. Note the rule in `.claude/CLAUDE.md` if it's a "do not break" invariant.

The system stays useful only if every contributor (including future-you) can read this file and rebuild any view without guessing.
