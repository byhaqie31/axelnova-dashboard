# UI Standard — axelnovaventures.com

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

### Component-family tokens

Larger component families get their own prefixed token group in `main.css` (light + `.dark`), documented with the family's pattern section:

- `--kanban-*` — team kanban board surfaces (§12.14)
- `--calendar-*` — team calendar grid + day chips (§12.15)

---

## 3. Typography

- **Body family:** Inter (fallback: SF Pro Display, system sans). Loaded via `@nuxtjs/google-fonts` with `display: swap`.
- **Display family:** Outfit (`--font-display`, weights 400/500/600), used on signature headlines via the `.font-display` class — it sets the family only, so weight/tracking come from utilities (e.g. the hero headline is `font-display font-medium tracking-tight`). Falls back to Inter so headings never flash in a system face. Also downloaded via `@nuxtjs/google-fonts`.
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
- `.font-display` — Outfit display family (weight/tracking via utilities)

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
| `.btn-pill-success` | Solid `--color-success` (green) + white text — WhatsApp / positive actions |
| `.btn-pill-warning` | Solid `--color-warning` (orange) + dark label — convert / upgrade actions (e.g. "Expand to detailed") |
| `.btn-pill-preview` | Soft `--color-warning-soft` fill + orange label — the live in-app "Preview" action (`AdminDocumentPreviewModal`), so it reads distinct from the neutral ghost "View PDF" beside it |
| `.btn-pill-danger` | Solid `--color-danger` (red) + white label — destructive actions (e.g. "Revert changes") |
| `.btn-pill-silver` | `--color-silver` metallic fill — neutral secondary actions (e.g. "Move back to draft") |

**Rule:** one primary CTA per screen. Secondary actions are ghost or silver; WhatsApp/positive actions use success green.

**Form-control height parity.** Inputs, selects, and buttons share a **44px** control height so they line up on the same row — buttons via `.btn-pill` (44px), text fields via `.contact-input` (44px). Never pair a `.btn-pill` with an ad-hoc short input (they misalign, as the referral "Tie" control did): use `.contact-input`, or set the field to `height: 44px`. When an input + button don't fit comfortably side by side, stack them full-width instead of shrinking either below 44px.

**Number inputs have no spinner.** The browser's up/down spin arrows are stripped globally in `main.css` — they never fit this UI. Use `type="number"` + `inputmode="numeric"` for a clean field with a numeric keypad on mobile.

### 6.1 Glass nav & chip surfaces

Two pill-family surfaces for floating overlays (introduced by the hero `HeroEpoch`). Both are theme-driven so they read in light **and** dark — never bind their backgrounds to `colorMode` in JS.

| Class | Style |
|---|---|
| `.glass-nav` | Frosted pill rail — `--nav-bg-scrolled` + `backdrop-filter: blur(24px)` + `--shadow-lg`, fully rounded. For floating nav/overlay pills over media. |
| `.pill-chip` | Shared chip surface — `--color-bg-elevated` + `--color-border` + `--shadow-sm`, fully rounded; hover → `--color-border-strong`. **One rule**, reused identically by the hero nav "Get in touch" pill and every marquee logo card. Size/padding via utilities at the call site. |

---

## 7. Components

Components organize by **scope**, not by atomic level.

### Folder structure

- `components/shared/` — used in 2+ portals (e.g. `BrandMark`, `ProjectCard`, `SectionHeader`)
- `components/shared/primitives/` — domain primitives (`PriceTag`, `StatusPill`, `ReferenceCode`, `DateRange`)
- `components/public/` — public landing site only
- `components/admin/` — admin portal only
- `components/portal/` — client portal only

### Rules

- We don't wrap `@nuxt/ui` components as atoms — the library is our primitive layer
- Genuine domain primitives (MYR formatting, status colors, reference codes) live in `shared/primitives/`
- Extract a shared component when used in 3+ places (rule of three)
- A component used in only 1 portal stays in that portal's folder, even if it could theoretically be reused

### Why not atomic design (atoms/molecules/organisms)?

`@nuxt/ui v4` already provides our atom layer. Tailwind v4 + CSS vars in `main.css` provide our styling primitives. Adding folder hierarchy for atomic levels would duplicate what the libraries already give us, while creating categorization overhead. We organize by **where a component is used** because that's the question we actually ask when adding new files. If this product ever splits into a sister product that should share components, extract into a real package (`@axelnova/ui`) at that point.

### Layouts

Three layouts, one per scope:

- `layouts/public.vue` — marketing site (header + footer + iridescent hairline)
- `layouts/admin.vue` — admin shell (topbar + collapsible sidebar; nav populated in Phase 3)
- `layouts/portal.vue` — client portal (calmer header, max-w-5xl content, minimal footer)

There is **no `default.vue`**. Every page must declare `definePageMeta({ layout: 'public' | 'admin' | 'portal' })` (or `layout: false` for fully standalone surfaces like `/admin/login` and `/proposals/[slug]`).

### Page folders mirror layout scopes

```
pages/
├── public/    → URLs DO NOT include "/public" (stripped by pages:extend hook)
├── admin/     → /admin/*
└── portal/    → /portal/*
```

`pages/public/about.vue` → `/about`. `pages/public/legal/terms.vue` → `/legal/terms`. The stripping is handled by a `pages:extend` hook in `nuxt.config.ts` (function `stripPublicPrefix`). Do not delete or rename it without auditing every public URL.

When Nuxt fixes the `(group)` route-group syntax in a future release, this hook can be removed in favor of `pages/(public)/...`. Probe it on each Nuxt upgrade with a test page; until then, the hook stays.

### `BrandMark`
- Variants: `default` (icon + wordmark), `compact` (smaller, used in admin topbar), `mark-only` (icon only)
- Wraps the canonical `.brand-logo-glow` drop-shadow — never reimplement the gradient/glow inline
- Used in `public.vue` header AND footer, `admin.vue` topbar, `portal.vue` header

### `VideoBackground`
- Full-bleed ambient mp4 layer behind page content — fixed, click-through, `z-index:-1`, `object-fit: cover`.
- Solid `--color-bg` always paints underneath, so a failed load degrades to the app background.
- Forces `muted` on mount (hydration can drop the prop and break autoplay); paused on first frame under `prefers-reduced-motion`.
- Used by the admin / team / partner login screens, each passing its own `src` footage behind the liquid-glass sign-in card.

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

### Domain primitives

- **`PriceTag`** — MYR-formatted (Intl `ms-MY`). Props: `min`, `max?`, `prefix?`, `compact?`. Renders ranges with en-dash, prefixed values with em-dash separator.
- **`StatusPill`** — status badge with semantic tone mapping. Props: `status`, `type` (`lead` | `quotation` | `project` | `invoice` | `milestone` | `referral` | `referral_partner` | `task` | `user`). Reads existing CSS tokens (`--color-accent`, `--color-success`, `--color-warning`, `--color-danger`); no new color tokens introduced. `referral` / `referral_partner` were added for the `/admin/referrals` hub (Task 2 of the portal restructure) — first real adoption of this primitive; earlier admin pages still use the older `AdminStatusPill.vue` (data-attribute driven, `main.css` tokens) and haven't been migrated. `task` (Task 5 — the tasks engine) maps the workflow spine: `open` neutral, `in_progress` info, `completed`/`paid` success, `payment_pending` warn. `user` (Task 8 — `/admin/users`) is derived client-side from `deactivated_at`, not a stored enum: `active` success, `deactivated` danger. Role itself (founder/marketer/engineer) is a separate chip built from `data/workspaceRoles.ts`, not this primitive — founder gets a distinct warning/gold tone + crown icon.
- **`TaskPayBadge`** — the task payment badge (Task 5, §12.14). Props: `state` (`none` | `pending` | `paid`), `amount` (RM, nullable). Renders **nothing** for `none` — payment is a card badge, never a kanban column, because most tasks carry no extra pay. `pending` reads warning, `paid` reads success; amount formatting matches `PriceTag` (Intl `ms-MY`, no cents). Shared by the admin tasks table, the team kanban card, and the calendar's completed log.
- **`ReferenceCode`** — monospace document-code display (e.g. `AXNQ-2026-0012`) with click-to-copy via `useClipboard`. Renders any string; falls back to plain span when `copyable={false}`.
- **`DateRange`** — Intl `en-MY` formatted dates. Formats: `short`, `long`, `relative`. Accepts optional `prefix` ("Valid until", "Issued").

### Filter pills
- 28px tall, 16px horizontal padding, hairline border.
- Active: solid `--color-text`, `--shadow-sm`.

---

## 8. Motion

**Single source of truth: [MOTION.md](MOTION.md)** — tokens (`app/utils/motion.ts`),
composables, Lenis smooth-scroll plumbing, and the rules for adding animations.
Highlights:

- **Tokens, never magic numbers:** durations 0.3/0.6/0.9/1.2s, default ease `power3.out`, staggers 0.08/0.1/0.14, reveals y: 52 from `top 85%`.
- **Dashboard register is faster:** 0.3–0.5s; no parallax/magnetic/SplitText on admin/portal.
- **Aurora drift:** 24s `ease-in-out` infinite alternate (background mesh).
- **Aurora line shift:** 14s ease-in-out infinite (navbar gradient hairline).
- **Page transition:** GSAP JS hooks on `<NuxtPage>` (`out-in`): leave 0.3s, enter 0.45s.
- **Reduced motion:** entrances are no-ops (content instantly visible), Lenis disabled; essential state transitions kept.

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

- [ ] Declare `definePageMeta({ layout: 'public' | 'admin' | 'portal' })` (or `layout: false` for standalone surfaces).
- [ ] Top section either centered hero or full-bleed with section padding `py-32` (public only).
- [ ] Call `useScrollReveal('.reveal')` once in `<script setup>` (public only — admin/portal don't use scroll reveal).
- [ ] All surfaces use `var(--color-bg-*)` / borders use `var(--color-border)`.
- [ ] Buttons use `.btn-pill-*` classes.
- [ ] Verified light + dark, mobile (375px) + desktop (1440px).
- [ ] No `opacity-0` on JS-animated elements.

---

## 12. Admin form patterns

These patterns are the source of truth for admin CMS forms (`/admin/services/categories/[id]`, `/admin/services/packages/[id]`, future admin entities). New admin forms must reuse them, not invent variants.

### 12.1 Page header — no eyebrow

Admin pages use a single `<h1>` for the page title. No "Admin · CMS" eyebrow above it.

**Why:** the sidebar + route already tell the user they're in admin. The eyebrow was redundant chrome that fought the page title for attention.

```vue
<div class="mb-6">
  <h1 class="text-[28px] font-bold tracking-tight" :style="{ color: 'var(--color-text)' }">
    {{ isNew ? 'New package' : 'Edit package' }}
  </h1>
</div>
```

Pages this applies to (audit before adding a new admin form): `services/index`, `services/categories/[id]`, `services/packages/[id]`, `projects/index`, `projects/[id]`. Any new admin index/edit page should match.

### 12.2 Toggle row-card

The canonical admin toggle. Full-width clickable card; the **whole row** is the click target, not just the switch.

**Anatomy** (left → right):
1. **Icon tile** — `size-9` rounded-lg square. Tinted with the on-color when active, neutral (`bg-elevated` + `text-tertiary`) when off.
2. **Label + subtitle** — title (`13px font-medium`) over a `11px text-tertiary` subtitle that explains what toggling does.
3. **Sliding switch** — track `2.25rem × 1.25rem`, knob `size-4` (white, `bg-white shadow`), slides from `left: 0.125rem` (off) to `left: 1.125rem` (on).

**State semantics:**
- **Off:** `border: var(--color-border)`, `background: var(--color-bg)`, label uses `text-tertiary`. Switch track is grey (see token gap below) — clearly inactive but **never** opacity-dimmed. The switch must look like a real, clickable control in both states.
- **On:** `border: <on-color>`, `background: var(--color-bg-elevated)`, label uses `text` (full). Switch track fills with the on-color. Knob stays white.

**Color semantics for the on-state:**

| Concept | On-color | Soft tile bg | Used for |
|---|---|---|---|
| Brand / "this is the chosen one" | `--color-accent` | `--color-accent-soft` | Featured, Default tab, primary on/off settings |
| Published / "visible to public" | `--color-success` | `--color-success-soft` | Active toggles |

> ✅ **Resolved.** `--color-success-soft` is now defined in `main.css` (light `rgba(48, 209, 88, 0.12)`, dark `rgba(48, 209, 88, 0.18)`). All admin forms migrated to `var(--color-success)` / `var(--color-success-soft)` — no more `#10b981` or `rgba(16, 185, 129, …)` in components.

> **Token gap (still open):** the off-state switch track is currently hardcoded `#d1d5db`. Add `--color-switch-off-track` (light: `#d1d5db`, dark: `rgba(255,255,255,0.18)`) to `main.css` so the off state still reads correctly in dark mode.

> **Token gap (still open):** add `--color-on-accent: #FFFFFF` (and matching `--color-on-success`) for foregrounds placed on filled accent/success surfaces. Components currently fall back via `var(--color-on-accent, #fff)`.

**Subtitle copy — be specific.** "Visible on the public services page" beats "Active". The subtitle is what disambiguates two toggles that look visually similar.

**One toggle per concern.** Don't bundle (e.g. don't make a single switch toggle both "active" and "featured"). A row-card per concern.

**Layout.** Stack vertically with `space-y-2` inside the card. Toggle group always sits below structured fields (slug, prices, etc.) and above form actions (Save / Cancel).

### 12.3 Sort-order pill picker

Replaces raw `<input type="number">` for `sort_order` columns on entities that live in an ordered list (categories, packages, projects).

**Anatomy** (full-width row, `flex items-center gap-1.5 flex-wrap`):

1. **Left chevron** — `i-lucide-chevron-left`, `size-9` button. Disabled at `sort_order <= 0` (`opacity-30`).
2. **Numbered pills** — one per occupied position in the current scope. `size-9` rounded-lg. Selected fills with `--color-accent` + white knob; unselected uses `bg-elevated` + `text`.
3. **Append pill** — dashed-border `i-lucide-plus` button representing the next available slot. Selected when `form.sort_order === nextAvailableSort`. Default for new records.
4. **Right chevron** — mirror of left, disabled at `sort_order >= nextAvailableSort`.

**Helper text** sits below: `+ auto-appends at position {{ nextAvailableSort }}. Click an existing number to insert there — the colliding row shifts down.`

**Behaviour contract:**
- For **new** records: default `form.sort_order = nextAvailableSort` once siblings have loaded (i.e. `[+]` highlighted).
- For **edit** records: `form.sort_order` reflects the current position; the matching pill is highlighted.
- Clicking a pill or chevron only updates `form.sort_order`. The actual reorder/shift happens **server-side** in `App\Support\SortOrder` (`placeNew` for create, `move` for update) — never duplicate the math client-side.
- The pill list is derived from `siblings` fetched via the entity's index endpoint (e.g. `GET /api/v1/admin/service-packages?service_category_id=N`). Refetch when the scope changes.

**Where it applies.** Every admin entity with a `sort_order` column. Future entities (orders, FAQ items, testimonials, etc.) get this same picker — never a number input.

### 12.4 Pastel section header (admin lists)

For grouped lists (e.g. service categories on `/admin/services` each containing packages), the **group header strip** uses the brand pastel so each card is visually bounded against the page background.

```vue
<header class="flex items-center gap-3 px-5 py-4 border-b"
  :style="{ borderColor: 'var(--color-border)', background: 'var(--color-accent-soft)' }">
  <!-- icon container (rgba white panel + accent icon) -->
  <!-- title + description in default text colors (readable on pastel) -->
  <!-- action buttons: white-tinted rgba background, neutral border, default text -->
</header>
```

**Rules:**
- Header background: `--color-accent-soft` (the existing token, do not invent a new pastel).
- Title text: `--color-text`. Description: `--color-text-secondary`. Both stay legible on the pastel without inversion.
- Action buttons (Edit / Add / Delete) sit on `rgba(255,255,255,0.5)` so they pop without competing with the pastel; the **Delete** button keeps `--color-danger` for the text — never invert destructive actions to white-on-coloured.
- The list of children below the header sits on the standard `--color-bg`, so each group reads as a card with a tinted cap.

**Do not** use full `--color-accent` (saturated brand) for these strips — that competes with primary CTAs and reads as too aggressive for content-grouping. Reserve solid accent for one primary CTA per screen (Section 6).

### 12.5 Cross-form consistency checklist

When adding a new admin form, verify:

- [ ] No "Admin · CMS" eyebrow (12.1).
- [ ] All boolean fields render as toggle row-cards (12.2), not native checkboxes.
- [ ] Any `sort_order` field renders as the pill picker (12.3), not a number input.
- [ ] Short fixed-list dropdowns (statuses, units, modes) render as a pill button group (12.6), not a native `<select>`.
- [ ] Variable / longer-list dropdowns render as the popover dropdown (12.7), not a native `<select>`.
- [ ] Icon-name fields use the curated icon picker (12.8), never a free-text Iconify input.
- [ ] Subtitle copy on every toggle is specific to the user-visible effect.
- [ ] On-colors match the semantic table (12.2): accent for "selected/featured/default", success for "published/visible".
- [ ] Knob in the switch is always white; only the track changes color.
- [ ] No hardcoded hex — use tokens (and add new tokens to `main.css` if missing).
- [ ] Click target is the whole card / pill, not just the switch / number.

### 12.6 Pill button group (short fixed lists)

Replaces `<select>` for finite enumerations: ETA units, statuses, units of measure, mode toggles. Uses the `.standard-pill` global class (in `main.css` alongside `.status-pill`).

**When to use:** option count is fixed and ≤ 8 (otherwise the row wraps awkwardly — switch to 12.7 popover dropdown).

**Anatomy:** `flex flex-wrap gap-1.5` of `.standard-pill` buttons. Each pill is rounded-full (9999px), `12px` font, `0.375rem 0.75rem` padding, single-line, hover gets `--color-border-strong`.

**Selected styling depends on the option's semantic colour:**
- Generic / no semantic tone → `borderColor: var(--color-accent)`, `background: var(--color-accent-soft)`, `color: var(--color-accent)`.
- Per-option semantic tone (e.g. project status: live → success, wip → warning, planning → accent) → use the option's `color` + `bg` from a shared status-options data file. See [data/projectStatuses.ts](frontend/app/data/projectStatuses.ts).

**Sources of truth for option lists.** Don't inline option arrays in the component. Put them in `data/<thing>Statuses.ts` so the same list powers both the form picker and any read-only badge elsewhere (ProjectCard, etc).

**Where it applies today:**
- `eta_unit` in [packages/[id].vue](frontend/app/pages/admin/services/packages/[id].vue) (4 options, generic accent tone)
- `form.status` in [projects/[id].vue](frontend/app/pages/admin/projects/[id].vue) (4 options, per-status semantic tones)
- `form.availability` in [team/profile.vue](frontend/app/pages/team/profile.vue) (2 options — Available/Busy, per-status semantic tones from [data/availabilityStatuses.ts](frontend/app/data/availabilityStatuses.ts); the same list drives the read-only dot/pill in [layouts/team.vue](frontend/app/layouts/team.vue))
- `form.priority` in [admin/tasks/index.vue](frontend/app/pages/admin/tasks/index.vue) (3 options, per-priority semantic tones from [data/tasks.ts](frontend/app/data/tasks.ts))
- `form.audience` in [admin/announcements/index.vue](frontend/app/pages/admin/announcements/index.vue) (3 options — Team/Partners/Everyone, per-audience semantic tones from [data/announcements.ts](frontend/app/data/announcements.ts); the same list drives the read-only audience pill in the list view)

### 12.7 Popover dropdown (variable / longer lists)

The standard "looks like an input, opens a panel" dropdown. Uses three global classes in `main.css`:

| Class | Role |
|---|---|
| `.standard-select-trigger` | The input-shaped button. Same `border-radius: 12px` and padding as `.contact-input`. On open (`aria-expanded="true"`) gains `--color-accent` border + `--shadow-glow`. |
| `.standard-select-panel` | Absolute-positioned panel, `top: calc(100% + 6px)`, `z-index: 50`, `max-height: 280px` with overflow-y, rounded-xl, `--shadow-lg`. |
| `.standard-select-option` | Row inside the panel — `0.5rem 0.625rem` padding, hover bg `--color-bg-secondary`, selected gets `--color-accent-soft` bg + accent text. |

**Required behaviours:**
- Trigger displays current selection (icon + label + chevron). Chevron rotates 180° when open.
- Click outside closes — implement with a `<div class="fixed inset-0 z-40" @click="open = false" />` invisible backdrop. Z-order: backdrop 40, panel 50.
- **Escape closes** — wire `onKeyStroke('Escape', …)` from `@vueuse/core` (already auto-imported via `@vueuse/nuxt`).
- Selected option shows `i-lucide-check` on the right.
- Panel slides in via the global `dropdown-panel` Vue Transition (defined in `main.css`): `opacity 0.15s + translateY(-4px → 0)`.

**When to use:** option count is variable, can grow with admin actions, or > 8.

**Where it applies today:** `service_category_id` in [packages/[id].vue](frontend/app/pages/admin/services/packages/[id].vue).

### 12.8 Curated icon picker

Free-typing Iconify names is forbidden — every icon-name field is a visual grid picker backed by a curated allowlist. This is what keeps the public surfaces (services page, project cards) visually consistent.

**Source of truth:** the allowlist lives in `data/<scope>Icons.ts`. Today: [data/serviceIcons.ts](frontend/app/data/serviceIcons.ts) (~26 icons grouped by Web / Engineering / Design / Product / Growth / Support).

**Anatomy:** `grid grid-cols-6 sm:grid-cols-9 gap-1.5` of square buttons (`aspect-square rounded-lg border`). Each icon button has:
- `:title` and `:aria-label` set to the human label (e.g. "SaaS / product launch"), so hover and screen reader both work.
- Selected: `--color-accent` border + `--color-accent-soft` background + accent-coloured icon.
- Unselected: `--color-border` + neutral.

**Below the grid:** a one-line caption echoing the current selection — `Selected: <code>i-lucide-globe</code> — Web presence` — so admins know the actual stored value.

Adding a new icon is a one-line addition to the allowlist. Do not let admins type names.

### 12.9 Admin shell

The `admin.vue` layout is the only place these patterns live; no need to duplicate them per page.

**Sticky desktop sidebar.**
- The `≥ md` sidebar is `position: sticky; top: 3.5rem` (clears the `h-14` sticky topbar) with `height: calc(100vh - 3.5rem)` and `self-start` (align-self: flex-start). Without `self-start` the flex child stretches to the full content height and has no room to stick — keep it.
- The inner `<nav>` is `overflow-y-auto` so long nav lists scroll inside the pinned rail instead of pushing it taller than the viewport. Only the main content column scrolls with the page.

**Sidebar nav items.** Use the global `.admin-nav-item` class (in `main.css`), not per-item inline `:style`. The selected state is driven by `:data-active="isAdminNavActive(item, route.path)"` — an attribute, not an inline background — so hover (`--color-bg-secondary`) still works on inactive items (an inline `background` would always beat a `:hover` class). Items are 44px tall, `14px` text, `size-4.5` icon, `12px` radius; active gets `--color-accent-soft` bg + accent text + `600` weight. The mobile drawer reuses the same class (including the Sign-out button).

**Grouped, collapsible nav (Phase 3a; regrouped in Task 1 of the portal restructure).** `adminNav` is `NavGroup[]` (`data/adminNav.ts`) — seven workflow groups (Overview · Sales pipeline · Billing · Growth · Partners · Catalog · Workspace). Render via `visibleAdminNav(role)` (two-level role filter, group + item; permissive until Phase 0 wires a role). Group headers use the global `.admin-nav-group-label` class: muted `--color-text-tertiary`, `11px` uppercase, `0.06em` tracking, with a chevron that rotates `-90deg → 0` on open. The header **is** the collapse toggle. One accent only (the active item) — no per-group colors, no per-item badges. Open/closed state persists in the `axn_admin_nav_groups` cookie (SSR-resolved, default open); the group owning the active route is force-open and can't be collapsed (`groupHasActive` short-circuits `toggleGroup`). The collapsed desktop rail drops labels and flattens groups to icons separated by a hairline.

**Collapsed-rail tooltips.** Every icon in the collapsed rail wraps its `NuxtLink` in a `UTooltip` (`:text="item.label"`, `:content="{ side: 'right', sideOffset: 10 }"`, `:delay-duration="150"`) so the icon names itself on hover. Style via the global `.admin-nav-tooltip` class passed as `:ui="{ content: 'admin-nav-tooltip' }"` — elevated surface (`--color-bg-elevated` + `--color-border` + `--shadow-lg`), `12px`/500 text, `8px` radius. Nuxt UI teleports the tooltip to `<body>`, which is why it works despite the rail's `overflow` clip and why it must carry its own tokened surface instead of inheriting from the sidebar. Don't fall back to the native `title` attribute (slow, unstyled, double-tooltips against this one). Both `admin.vue` and `team.vue` rails use the identical pattern.

**Header brand marker.**
- Uses the shared `<BrandMark to="/admin" wordmark="Admin Portal" />` (default variant — same `size-7.5` icon + `text-[15px]` wordmark as the public navbar) so the favicon + drop-shadow glow + gradient text treatment stay visually identical to public.
- The `wordmark` prop is the only override; defaults to "Axel Nova Ventures" for public layouts, set explicitly per layout when it differs.
- `to` points at `/admin`, not `/`, so clicking the brand returns to the admin dashboard.
- Do not reintroduce a custom orb/aurora wrapper around the favicon — the brand-logo-glow drop-shadow on `BrandMark` is the canonical treatment everywhere it appears.

**Browser tab title.**
- The admin layout sets `useHead({ title: 'Admin Portal' })` once. **Per-page admin files do not set their own `title`** — relying on the layout keeps the tab title stable as the user moves between admin sections (Dashboard, Quotations, Orders, …). If you genuinely need a per-page title for SEO or external linking, that's a sign the page isn't really admin-internal — reconsider whether it should live under the admin layout.
- Login (`/admin/login`) is the exception — it uses a different layout (pre-auth) and keeps its own title.

**Mobile floating drawer** (`< md` breakpoint).
- Detached from edges: `left-3 / top-17 / bottom-3`, `rounded-2xl`, `shadow-2xl`, internal `overflow-y-auto`.
- Backdrop overlay: `rgba(0, 0, 0, 0.32)` + `backdrop-filter: blur(2px)`, sits at `z-20`, drawer at `z-30`. Backdrop click closes the drawer.
- Two scoped Vue Transitions: `drawer-backdrop` (opacity 0.2s) and `drawer-panel` (opacity 0.2s + translateX(-12px → 0) over 0.25s with `cubic-bezier(0.32, 0.72, 0, 1)`).
- Auto-closes on route change via `watch(() => route.fullPath, …)`.
- Honors `prefers-reduced-motion`.

These exist as scoped styles on `admin.vue`. If a second mobile drawer is ever needed elsewhere (portal layout?), promote the CSS to global classes before duplicating.

### 12.10 Mobile responsiveness baseline

Admin is used on mobile too. Every new admin page must be usable at **375px** (iPhone SE) and **414px** (iPhone 14 Pro). The patterns below are mandatory, not optional.

**Container padding.** Use `max-w-[7xl|3xl] mx-auto px-4 sm:px-6 pt-10 pb-32`. Never `px-6` alone — that wastes 12px per side on tiny screens.

**Desktop table surface.** The `hidden md:block` table wrapper uses the global `.admin-table-card` class (in `main.css`) — a solid elevated card (`--color-bg-elevated`) with `--shadow-sm`, a `--color-bg-secondary` header band, and `16px` radius. Do **not** leave the table transparent over the page background; it reads as one flat tone. Each `<tbody>` row uses `.admin-table-row` (handles the bottom hairline, `cursor-pointer`, subtle `--color-bg` zebra on even rows, and a `--color-accent-soft` hover) — drop the old per-row `border-b cursor-pointer hover:bg-(…)` utilities and the inline `border-color`/header `background` styles. All five admin lists (dashboard, inquiries, quotations, orders, referrals) share these two classes; keep them in sync there.

**Tables → cards on mobile.** Tables become unusable below `md`. Render the same data twice: a `<table>` wrapped in `hidden md:block` for desktop, and a `md:hidden space-y-2.5` card list for mobile. Each mobile card is a single `<button>` (whole card is clickable, navigates to the row's detail page) with this layout:

```
┌──────────────────────────────────────┐
│ [primary id]            [status pill] │
│ Name                                  │
│ subtitle (email / hint)               │
│ ─────────────────────────────────── │
│ [primary value]      [secondary]      │
│ [date / footer text]                  │
└──────────────────────────────────────┘
```

Class scaffold:

```vue
<button class="w-full text-left rounded-xl border p-4 transition-colors hover:bg-(--color-bg-secondary)"
  @click="navigateTo(detailUrl)">
  <div class="flex items-start justify-between gap-3 mb-2"><!-- id + status --></div>
  <p class="text-[13px] font-medium">…name…</p>
  <p class="text-[11px] mb-3" style="color: var(--color-text-tertiary)">…subtitle…</p>
  <div class="pt-2 border-t" style="border-color: var(--color-border)">
    <!-- value + meta footer -->
  </div>
</button>
```

The desktop table still gets `<div class="overflow-x-auto">` inside the outer rounded card — it's the safety net if anyone forgets to add the mobile cards.

**Don't use `display: table` media-query swaps** — duplicating the markup keeps the desktop and mobile layouts independent (different copy, different ordering) without shoehorning a table cell into a stacked card via CSS gymnastics. Yes, the data appears twice in the DOM. That's the trade.

**Action button rows.** Right-aligned action groups (Edit / Delete / + Package etc.) must wrap to a new row at narrow widths instead of crowding the title. Use `w-full sm:w-auto sm:shrink-0 justify-end` on the button container, and `flex flex-wrap items-center gap-3` on the parent row. Mobile gets buttons on row 2 right-aligned; desktop keeps them inline.

**Detail-page sidebars.** Use `grid lg:grid-cols-[1fr_300px] gap-8 items-start` — `grid` alone (no `grid-cols-X`) renders a single column, so children stack naturally below `lg`. Don't add `sm:grid-cols-1` (redundant).

**Popover dropdowns** must not overflow the viewport. The global `.standard-select-panel` already has `max-width: calc(100vw - 24px)` for safety. If you build another floating panel, replicate that clamp.

**Form rows.** `grid sm:grid-cols-2 gap-4` is the standard two-column form pattern — always with the `sm:` prefix so it stacks at mobile. `grid grid-cols-2` alone (no `sm:`) is forbidden.

**Action buttons in detail pages** (status pickers, etc.) must already use the pill-button group (12.6), which wraps via `flex flex-wrap`.

**Button icon spacing.** `.btn-pill` carries a built-in `gap` (≈8px) so a `<UIcon>` never sits flush against the label — write `<UIcon … /> Label` and the gap is handled. Don't add manual spacer spans / `&nbsp;`; only add a `gap-*` utility on the button when a specific tighter/looser gap is wanted (it overrides the base).

**Search bars** in admin index pages must use [`<AdminExpandingSearch v-model=… placeholder=… />`](frontend/app/components/admin/ExpandingSearch.vue), not a raw `<input type="search">`. Default state is an icon-only round button (~36px); clicking it slides the input open and auto-focuses it. Blurring an empty input collapses back to the icon. Pre-filling `v-model` from a query string opens the input on mount automatically. Reasons:
- At mobile widths the full-width search input dominates the filter bar; the icon recovers that space.
- Most admin queries are quick "open the page → maybe search" — the icon expresses that intent better than a permanently-open field.
- Escape clears + collapses (`@keydown.escape="clearAndCollapse"` is built-in).

**Audit before merging.** Open the page at 375px (Chrome DevTools mobile preset). Look for: horizontal scroll on the body, action buttons overlapping titles, modals/popovers running off-screen, sticky panels covering content. Fix before merging.

### 12.11 List filter row (standard)

Every admin index page uses the **same filter row** so they read identically — `flex flex-wrap items-center gap-3` containing, in order:

1. [`<AdminExpandingSearch>`](frontend/app/components/admin/ExpandingSearch.vue) — left.
2. [`<AdminFilterMenu>`](frontend/app/components/admin/FilterMenu.vue) — a funnel button beside the search that opens a popover holding the page's **secondary** filters (type / method / gateway …), each an [`<AdminFilterPills>`](frontend/app/components/admin/FilterPills.vue) group (§12.6). Pass `:active-count` (count of non-empty secondary filters) for the badge + accent state and `@clear` to reset them. Omit it entirely when a page has no secondary filters (e.g. Orders).
3. [`<AdminStatusFilter>`](frontend/app/components/admin/StatusFilter.vue) — **right**, via `class="ml-auto"`, carrying `:total` (record count) + the primary Status filter.

`Total | Status` always lives on the right and nothing else does; everything page-specific collapses into the funnel, keeping the row uncluttered at every width.

### 12.12 Query-param pill tabs

The standard "hub page with switchable views" pattern — introduced by the `/admin/referrals` merge (Task 2 of the portal restructure, merging the old `/admin/referral-partners` pages in as a "Referrers" tab alongside "Referrals"). Use this whenever an admin page needs 2–4 mutually-exclusive views of related data on one URL, instead of a native `<select>` or a router-tab library component.

**Anatomy:** a bounded segmented track (`.tab-track`), not a loose row of pills — this is what reads as a single control rather than a filter group (contrast with §12.6, which is intentionally unbounded). Each `.tab-pill` is a plain `<button>`; the active one gets an elevated `--color-bg-elevated` fill + `--shadow-sm`, matching the "hotel lobby" restraint (no accent color, no underline animation — just a subtle raised state).

```vue
<div class="tab-track" role="tablist" aria-label="…">
  <button
    v-for="tab in TABS" :key="tab.value" type="button" role="tab"
    :aria-selected="activeView === tab.value"
    class="tab-pill"
    @click="setView(tab.value)"
  >{{ tab.label }}</button>
</div>
```

**State contract — query-param is the single source of truth:**
- Tab state lives in `?view=<value>`, read via `computed(() => normalizeView(route.query.view))` — not a separate `ref` kept in sync with a watcher. One source of truth avoids drift bugs.
- `normalizeView()` maps anything that isn't a recognised value (missing, malformed, stale) to the **default** — the first tab. Never throw or redirect on a bad param; just render the default.
- Switching tabs calls `router.replace({ query: { ...route.query, view } })` — **replace, not push**, so clicking between tabs doesn't spam browser-back history with one entry per click.
- Because `route.query` is populated from the request URL during SSR, a hard refresh on a deep link (`/admin/referrals?view=referrers`) renders the correct tab server-side — no client-only flash.

**Where it applies today:** `/admin/referrals` (`Referrers` | `Referrals`, default `Referrers`). First use — the CSS lives scoped on that page (`<style scoped>`), not yet in `main.css`. Promote `.tab-track`/`.tab-pill` to global classes (mirroring the §12.9 mobile-drawer precedent: single-use patterns stay scoped, promote once a second page needs them) if another hub page adopts this.

### 12.13 Slideover panel

A right-edge overlay for a detail view that's one click deep from a list row, without leaving the list — introduced by the same `/admin/referrals` merge (the old `/admin/referral-partners/[id].vue` full page became the Referrers tab's slideover). Chosen over `@nuxt/ui`'s `USlideover` because this codebase has no prior `USlideover` usage and its theming is Tailwind-slot based (`ui` prop overrides), which fights the CSS-var token system rather than consuming it; the bespoke `Teleport` + scrim + sliding-panel shape was already proven on this exact page (the quotation-picker drawer in `referrals/[id].vue`) and on confirm dialogs across admin pages, so generalising that shape keeps one visual language instead of introducing a second.

**Anatomy:**
- `Teleport to="body"` + `<Transition name="slideover">` wrapping a scrim (`.slideover-scrim`, `rgba(0,0,0,0.4)` + `blur(3px)`, click-through only via `@click.self` so clicks inside the panel don't close it) and the panel itself (`.slideover-panel`, `width: 100%; max-width: 480px`, right-aligned via the scrim's `flex justify-end`, `height: 100%`, `box-shadow: var(--shadow-lg)`).
- Panel structure: `.slideover-head` (title/subtitle + `.slideover-close` circular icon button) → `.slideover-body` (`flex: 1; overflow-y: auto`) for the actual content.
- At 375px the panel is naturally full-bleed (`100%` < `480px` cap); no separate mobile layout needed — this is why the pattern works without a `hidden md:block` / `md:hidden` split.

**Motion:** dashboard register (§8) — scrim opacity `0.3s ease`, panel `transform: translateX(100% → 0)` over `0.35s cubic-bezier(0.32, 0.72, 0, 1)` (same curve as the quotation-picker drawer). Honor `prefers-reduced-motion: reduce` (drop both transitions).

**Layering with a confirm dialog.** If an action inside the slideover needs a confirm-before-act step (e.g. approve / reset passcode), the confirm overlay must sit *above* the slideover: slideover scrim `z-index: 90`, confirm overlay `z-index: 100` (same two-layer convention as the tie/untie confirm above the quotation-picker drawer in `referrals/[id].vue`). Wire `Escape` to close the topmost layer first (confirm, if open) before the slideover.

**Where it applies today:** `/admin/referrals` (Referrers tab → referrer detail), `/admin/tasks` (create/edit task panel — second adopter, Task 5), `/admin/announcements` (create/edit panel — third adopter, Task 6), and `/admin/users` (create/edit user panel — fourth adopter, Task 8; same class names + motion, CSS still scoped per page). Four scoped copies now exist, which is the trigger point for the "next page that adopts it" promotion rule above — promoting `.slideover-*` to global classes in `main.css` and stripping all four scoped copies is queued as a follow-up sweep, not done in this pass.

### 12.14 Team kanban board

The `/team/tasks` board (Task 5 — the tasks engine). Three **work** columns only — **Available → In progress → Complete** — moved by buttons, not drag-and-drop (premium-minimal, and buttons survive 375px + screen readers where DnD doesn't). **Payment is a card badge, never a column**: most tasks carry no extra pay, so a "payment" column would sit empty and misread the board as a money pipeline. The badge is the `TaskPayBadge` primitive (§7): `none` renders nothing; `pending`/`paid` render the RM amount + state chip (warning / success).

**Tokens (`main.css`, light + `.dark`):** the `--kanban-*` family — `--kanban-col-bg` / `--kanban-col-border` (columns are quiet sunken wells) and `--kanban-card-bg` / `--kanban-card-border` (cards are elevated surfaces that pop off them, `--shadow-xs`). Never hardcode these surfaces.

**Anatomy:** `.kanban-col` (16px radius well, 12px padding) → `.kanban-col-head` (icon + label + `.kanban-count` chip) → a `flex flex-col gap-2.5` stack of `.kanban-card`s (12px radius, 14px padding). Card contents top-to-bottom: title (13px semibold), 2-line clamped description, `.kanban-card-meta` row (priority tint chip from `data/tasks.ts`, relative deadline — overdue reads `--color-danger` — duration estimate, `TaskPayBadge`), then the action button(s).

**Column semantics + actions:**
- **Available** = the shared pool (open + unassigned, action **Pick up** → claim: assignee=me + in_progress in one gesture) *plus* my admin-assigned-but-unstarted tasks, listed first with an "Assigned to you" tag and a **Start** action.
- **In progress** = my in-flight tasks. **Complete…** opens a dialog with a *required* note textarea (the note becomes a timestamped line on the task log); **Release** returns the task to the pool (unassigns).
- **Complete** = my `completed` / `payment_pending` / `paid` tasks — read-only cards at `opacity: 0.82`, status via `StatusPill type="task"`.

**Responsive:** desktop is a 3-col grid (`grid-cols-1 md:grid-cols-3`); below 768px the columns **stack vertically, Available first** — chosen over horizontal scroll-snap because a single scroll axis is calmer and the pool ("what can I grab?") deserves the first screenful.

**A stale claim** (someone else picked the task up first) surfaces as a 409 → error toast + board refetch; the card simply disappears.

### 12.15 Team calendar

The `/team/calendar` month view (Task 5) — a **view over the tasks table** (deadlines + completed dates), no table of its own. Data comes from the same `GET /v1/team/tasks` feed as the kanban.

**Tokens (`main.css`, light + `.dark`):** the `--calendar-*` family — `--calendar-cell-bg` / `--calendar-cell-muted-bg` (in-month vs neighbour-month cells), `--calendar-grid-border`, `--calendar-today-ring` (accent circle on today's day number / accent border on today's agenda card), and the chip pair `--calendar-chip-mine-{fg,bg}` (accent — my tasks) vs `--calendar-chip-pool-{fg,bg}` (neutral — unclaimed pool tasks), so "mine vs could-be-mine" is one glance.

**Anatomy:** header row = title + month nav (`.cal-nav-btn` circular prev/next + a ghost **Today** pill + the month label). Desktop grid: Monday-first 7-col, weekday header strip, `.cal-cell` (min-height 96px) holding `.cal-daynum` (today gets the ring fill) and up to **3** `.cal-chip`s (truncated title + a 5px priority-tinted dot from `data/tasks.ts`) with a "+n more" overflow line. Below the grid, a **Completed in {month}** log: one row per task completed in the visible month — check icon + title + `TaskPayBadge` + completion date — inside a divided `--color-bg-elevated` card.

**Responsive:** below 768px the grid gives way to a **stacked agenda list** (only the days of the visible month that carry deadlines, each a card with its chips; today's card border uses the today ring token) — chosen over dots-in-a-grid because a list needs no tap-to-reveal step at 375px.

### 12.16 Data-table row actions

Row action buttons in the admin data tables (§12.9) use `.btn-table-action` (in `main.css`), **not** the full-size `.btn-pill` — the base pill is 44px tall with 24px padding and reads oversized inside a dense cell. `.btn-table-action` is a small (28px) bordered pill of `icon + label`: neutral by default (Edit, Reset), `.is-accent` for positive actions (Approve, Reactivate, Mark paid), `.is-danger` for destructive ones (Deactivate, Delete). The accent/danger variants use **soft tints** (`--color-accent-soft` / `--color-danger-soft`), never the heavy filled-variant drop-shadows, so a row of actions stays quiet.

**Rules:**
- Every action is `<UIcon name="i-lucide-…" class="size-3.5" /> Label` — icon **and** wording (no icon-only buttons in tables; they read as ambiguous). Common map: Edit → `pencil`, Delete → `trash-2`, Deactivate → `user-x`, Reactivate → `user-check`, Approve → `check`, Reset → `refresh-cw`, Mark paid → `banknote`.
- Any table with row actions carries an **"Actions"** column in its `<th>` list (last column).
- `:disabled` is handled by the class (opacity + `not-allowed`) — don't add manual `opacity-*` / `cursor-not-allowed` utilities.
- Applies today to `/admin/{users, tasks, referrals, announcements}` — the only admin tables with per-row buttons (every other list opens on row-click).

---

## 13. Updating this doc

Whenever a new token, component, or motion rule is introduced:
1. Add it to `app/assets/css/main.css`.
2. Document it here in the matching section.
3. Note the rule in `.claude/CLAUDE.md` if it's a "do not break" invariant.

The system stays useful only if every contributor (including future-you) can read this file and rebuild any view without guessing.
