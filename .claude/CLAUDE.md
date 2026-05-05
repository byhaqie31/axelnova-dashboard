# axelnova-dashboard — Claude Project Guide

Senior-engineer portfolio for Ahmad Baihaqie (Qie). Hosted at axelnova.tech.

## Tech stack

- **Nuxt 4** (`^4.4.4`) with `app/` source layout
- **Vue 3** (`^3.5.x`) + **TypeScript**
- **@nuxt/ui v4** (Tailwind CSS v4 under the hood)
- **GSAP** + **ScrollTrigger** (client-only plugin)
- **@nuxtjs/google-fonts** — Inter
- **@vueuse/nuxt** for composables
- Color-mode support via `useColorMode()` (provided by `@nuxt/ui`)

## Project layout

```
app/
  app.vue                Root entry (UApp + NuxtLayout + NuxtPage)
  layouts/default.vue    Sticky nav + footer with iridescent gradient hairline
  pages/                 index, projects, services, about, projects/[id], proposals/[slug]
  components/shared/     ProjectCard, SectionHeader
  composables/           useScrollReveal (GSAP + ScrollTrigger wrapper)
  plugins/gsap.client.ts Registers gsap + ScrollTrigger
  data/projects.ts       Project list source
  assets/css/main.css    Design tokens + utility classes
public/                  Static assets (cv.pdf, axelnovaicon.svg, etc.)
```

## Design system

Single source of truth: **[UI-Standards.md](../UI-Standards.md)** at the repo root. All color, typography, spacing, motion, and component decisions live there. Do not invent new tokens — extend `app/assets/css/main.css` and update `UI-Standards.md` together.

Key principles:
- Apple-inspired premium minimalism (graphite + Apple Blue + iridescent accents)
- All theming via CSS variables — never hardcode hex in components
- Both light and dark mode are first-class
- Animations are subtle (150–300ms micro, ≤700ms reveal), respect `prefers-reduced-motion`

## Color-mode FOUC rule (important)

Never bind layout backgrounds to `colorMode.value` in templates — `colorMode` resolves to a default during SSR and flips after hydration, causing a gray flash on refresh. Use CSS variables (`--nav-bg-top`, `--nav-bg-scrolled`, etc.) and let `:root` / `.dark` rules handle the swap. `@nuxt/ui` injects the `.dark` class before paint, so vars resolve correctly from the first frame.

## Scroll-reveal rule

`useScrollReveal('.reveal')` is the only API for fade-in-on-scroll. Do not add static `opacity-0` Tailwind classes to elements that depend on JS to reveal — if GSAP fails, content must still be visible. Use `gsap.set()` inside `onMounted` to set the initial hidden state instead.

## Conventions

- Inline `:style` is fine for one-off CSS-var lookups (e.g. `style="color: var(--color-text-secondary)"`). Anything reusable goes in `main.css`.
- Use `<UIcon name="i-lucide-…">` from Iconify for icons. Never emojis.
- Prefer `tracking-tighter` / `tracking-tight` over arbitrary `tracking-[…]` values.
- Pages should `useScrollReveal('.reveal')` once at script-setup and add `class="reveal"` to sections.
- Hero animations are page-specific — set initial state via `gsap.set()` in `onMounted`, not via Tailwind.

## Commands

```bash
# Local dev (host node)
npm install
npm run dev               # http://localhost:3000

# Production build & preview locally
npm run build
npm run preview

# Docker — local dev (hot reload, volume-mounted)
docker compose -f docker-compose.dev.yml up --build

# Docker — production image
docker compose up -d --build
docker compose logs -f axelnova-dashboard
```

Production runs at `127.0.0.1:3003` (reverse-proxied to `axelnova.tech`).

## Deployment

Container only listens on localhost — a host-level reverse proxy (Caddy/Nginx/Traefik) terminates TLS and forwards to `127.0.0.1:3003`. Match this convention when adding new services.

## When making UI changes

1. Read `UI-Standards.md` first to see existing tokens.
2. If a new pattern is needed, extend `main.css` (semantic tokens, not raw hex).
3. Update `UI-Standards.md` so the next session inherits the decision.
4. Verify in **both** light and dark mode.
5. Test on mobile (375px) and respect `prefers-reduced-motion`.
6. Do not break the FOUC rule — no `colorMode.value` in template `:style` for layout surfaces.
