# OG images (link previews)

The picture WhatsApp, LinkedIn, X and Facebook show when someone pastes a link. Public pages get a **generated card** with their own label and headline; a few keep a static image.

Built on [`nuxt-og-image`](https://nuxtseo.com/docs/og-image) v6 with the **Takumi** renderer (`@takumi-rs/core`, which ships a musl binary for the Alpine image). A card is a Vue component rendered to a 1200×630 PNG on the server the first time its URL is requested, then cached.

## Which page shows what

| Page | Preview |
|---|---|
| `/` | Static `public/og-image.jpg` (the hand-made brand card) |
| `/about`, `/company`, `/services`, `/projects`, `/contact`, `/quote`, `/partners`, `/partners/refer`, `/blog` | `SiteCard` with a fixed label + the page's own headline |
| `/services/{slug}` | `SiteCard`: category name + clipped description |
| `/projects/{id}` | `SiteCard` with the project's `cover_image_url` in a browser frame (tile art when it has none) |
| `/blog/{slug}` | The post's cover image; `SiteCard` (format + title) only when it has no cover |
| `/feedback/{token}` | `FeedbackCard` (dark): "How did we do on {project}?" |
| Legal pages, `/quote/success`, logins, anything else | Static `og-image.jpg` (the `nuxt.config` head default) |

## Templates

- [components/OgImage/SiteCard.takumi.vue](../../frontend/app/components/OgImage/SiteCard.takumi.vue) — light glass card. Props: `label`, `headline`, `subline?`, `footer?` (the address pill), `image?` (screenshot). Long headlines step down in size.
- [components/OgImage/FeedbackCard.takumi.vue](../../frontend/app/components/OgImage/FeedbackCard.takumi.vue) — dark card. Prop: `project?`.
- [components/og/OgTileArt.vue](../../frontend/app/components/og/OgTileArt.vue) — the glass-tile motif of `og-image.jpg`, redrawn in CSS (shared by both).

The `.takumi.vue` suffix picks the renderer and is required. Anything in `components/OgImage/` is treated as a card template, which is why the shared art lives in `components/og/`.

**Colours are literal hex** in these three files. That's deliberate: the renderer never sees `main.css`, so `var(--color-…)` can't resolve. Each value has a comment naming the token it mirrors. Change the token → change the mirror.

**Renderer quirks found while building:**
- `→` (U+2192) isn't in the latin font subset, so it renders as a missing-glyph box. The arrow is an inline `<svg>`.
- Put dynamic text in its own element (`<span>{{ footer }}</span>`) and keep text on the same line as its tag. A comment or newline beside a text node can drop or indent the text.

## Adding a card to a page

Pages on `usePublicSeo()` pass `card`:

```ts
usePublicSeo({
  title: 'Websites & Digital Projects | Axel Nova Ventures',
  description: '…',
  path: '/projects',
  card: { label: 'WORK', headline: 'Selected work and products.', subline: 'Live client work, products and experiments.' },
})
```

`image` still wins over `card`: a prepared picture such as a blog cover is used as-is. Pages that set `useSeoMeta` themselves call the module directly, and must **not** also set `ogImage`/`twitterImage`:

```ts
defineOgImage('SiteCard', { label: 'SERVICES', headline, subline, footer: ogFooter('/services/web') }, { alt: headline })
```

Helpers from [composables/usePublicSeo.ts](../../frontend/app/composables/usePublicSeo.ts): `ogFooter(path)` shows the page address while it fits (otherwise just the domain), and `clipText(text, max)` clips at a word boundary.

**Preview while designing:** Nuxt DevTools → OG Image tab (hot-reloads the template), or open the page's `og:image` URL on the dev server.

## Privacy

Card props are encoded into the image URL (plain text, or base64, which anyone can decode), and so is the page path. For `/feedback/{token}`, the image URL therefore contains the token and the project name. That's accepted: the URL only appears in the HTML of the token-gated page, so only people who already have the token can see it.

**Never pass a client's name, email or an amount to a card.** The feedback card takes the project label only.

## Caching and secret

- **Cache:** rendered PNGs are kept in memory for 72h (lost on restart/deploy) and served `Cache-Control: public, immutable`, so Cloudflare can cache them at the edge. Because the text is in the URL, changing a headline or renaming a project produces a new URL. A stale card is never served.
- **Previews already shared:** WhatsApp and Facebook cache each page's preview themselves. To refresh one after a change, re-scrape the page in the [Facebook Sharing Debugger](https://developers.facebook.com/tools/debug/).
- **Signing:** image URLs are signed; a tampered URL gets a 403. The secret comes from **`NUXT_OG_IMAGE_SECRET`** in `frontend/.env.production`. Without it the module auto-generates one per build, which changes on every deploy and breaks every previously shared image URL. Setup: [DEPLOY.md § OG image secret](../global/DEPLOY.md#og-image-secret).
- **Route rules:** don't add a `/**` `swr`/`isr`/`cache` rule. It would catch `/_og/**` too. The existing `/**` rule only sets headers.

## Fonts

The renderer only sees fonts declared through **`@nuxt/fonts`** with `global: true`, so the site uses `@nuxt/fonts` (Inter + Outfit, self-hosted under `/_fonts`) instead of `@nuxtjs/google-fonts`. A family used on a card but not declared there falls back to the renderer's bundled Inter. The `SF Pro` names in the CSS fallback stacks are declared with `provider: 'none'` so they're never looked up.
