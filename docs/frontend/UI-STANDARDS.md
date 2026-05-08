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
- **`StatusPill`** — status badge with semantic tone mapping. Props: `status`, `type` (`lead` | `quotation` | `project` | `invoice` | `milestone`). Reads existing CSS tokens (`--color-accent`, `--color-success`, `--color-warning`, `--color-danger`); no new color tokens introduced.
- **`ReferenceCode`** — monospace `AXN-YYYY-NNNN` display with click-to-copy via `useClipboard`. Falls back to plain span when `copyable={false}`.
- **`DateRange`** — Intl `en-MY` formatted dates. Formats: `short`, `long`, `relative`. Accepts optional `prefix` ("Valid until", "Issued").

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

**Search bars** in admin index pages must use [`<AdminExpandingSearch v-model=… placeholder=… />`](frontend/app/components/admin/ExpandingSearch.vue), not a raw `<input type="search">`. Default state is an icon-only round button (~36px); clicking it slides the input open and auto-focuses it. Blurring an empty input collapses back to the icon. Pre-filling `v-model` from a query string opens the input on mount automatically. Reasons:
- At mobile widths the full-width search input dominates the filter bar; the icon recovers that space.
- Most admin queries are quick "open the page → maybe search" — the icon expresses that intent better than a permanently-open field.
- Escape clears + collapses (`@keydown.escape="clearAndCollapse"` is built-in).

**Audit before merging.** Open the page at 375px (Chrome DevTools mobile preset). Look for: horizontal scroll on the body, action buttons overlapping titles, modals/popovers running off-screen, sticky panels covering content. Fix before merging.

---

## 13. Updating this doc

Whenever a new token, component, or motion rule is introduced:
1. Add it to `app/assets/css/main.css`.
2. Document it here in the matching section.
3. Note the rule in `.claude/CLAUDE.md` if it's a "do not break" invariant.

The system stays useful only if every contributor (including future-you) can read this file and rebuild any view without guessing.
