# SEO — public site

How the public site (axelnovaventures.com) is set up for search, and the rules new pages follow. Target searches: **web development / website developer / digital studio** in **Kuala Lumpur / Malaysia**. Last audited 2026-09-29; copy rewritten the same day. The current copy is in [SITE-COPY.md](./SITE-COPY.md).

## Per-page rules

- **Aim for titles around 60 characters and descriptions around 155.** These are editing targets for display width, not Google rules (Google may rewrite either). Front-load what the page offers and where (e.g. `Web Design & Development in Kuala Lumpur | Axel Nova`). Keep every page's title and description distinct. Set them with `usePublicSeo({ title, description, path })` ([composables/usePublicSeo.ts](../../frontend/app/composables/usePublicSeo.ts)), which also writes canonical, OG and Twitter tags. Pass `card` for a generated per-page link-preview image — see [OG-IMAGES.md](./OG-IMAGES.md). The homepage sets its own via `useSeoMeta` in [pages/public/index.vue](../../frontend/app/pages/public/index.vue).
- **Exactly one H1 per page.** Page titles built with `SectionHeader` pass `as="h1"` (it renders identically to the default h2 — letter-spacing is pinned so main.css's `h1 {}` rule doesn't tighten it). Every other `SectionHeader` stays an h2.
- **Say it in the copy.** Rankings follow the words on the page: the homepage lede, the `/services` intro and each service page's H1 name the service and the location in plain words. The tech stack belongs on service pages, not in headlines.
- **Data-driven text is clipped.** Blog posts without an SEO description, and service categories without an `enrichmentBySlug` entry, clip their fallback description to 155 characters at a word boundary; blog titles only get the ` — Axel Nova Ventures` suffix while they still fit.

## Service pages

The service list is admin data (`GET /v1/services`). Each category gets `/services/{slug}`; [pages/public/services/[slug].vue](../../frontend/app/pages/public/services/[slug].vue) layers optional per-slug SEO copy (`enrichmentBySlug`: `seoTitle`, `seoDescription`, a keyword-led `h1`, hero copy, FAQs) on top. A category without an entry still renders with its admin name and description. The footer's **Services** column is built from the same API, so every service page is linked from every page of the site — that's how crawlers find them (the `/services` tabs only link the active one).

## Structured data (JSON-LD)

| Page | Types |
|---|---|
| `/` | `ProfessionalService` (`@id` `…/#business`: name, phone, email, Kuala Lumpur, `areaServed: Malaysia`, `knowsAbout`, `sameAs`) + `WebSite` |
| `/about` | `Person` (`worksFor` → `#business`) |
| `/services/{slug}` | `Service` (`provider` → `#business`) + `BreadcrumbList` + `FAQPage` |
| `/blog/{slug}` | `BlogPosting` + `BreadcrumbList` |

**Keep the business name, locality and phone identical to the Google Business Profile** — mismatches weaken the local signal. Add the profile's Google Maps URL to the `sameAs` list on the homepage node once it's known.

## Sitemap and robots

`@nuxtjs/sitemap` builds `/sitemap.xml` at request time: the static public pages, plus [server/api/__sitemap__/urls.ts](../../frontend/server/api/__sitemap__/urls.ts), which adds published blog posts, the admin's service categories and the projects (each source fails independently). `sitemap.exclude` in [nuxt.config.ts](../../frontend/nuxt.config.ts) removes everything private — admin, portal, proposals, feedback, investor, the team workspace and the signed-in partner pages (`/partners` and `/partners/refer` stay). Those pages are also `noindex`; don't list a `noindex` page in the sitemap. A new service, project or post appears automatically — nothing to redeploy.

## Outside the code

- **Google Search Console:** submit `https://axelnovaventures.com/sitemap.xml` and resubmit after big changes.
- **www:** `www.axelnovaventures.com` should 301 to the apex (it currently answers 200; canonicals point at the apex, which softens it). Fix at the edge — a Cloudflare redirect rule (`www.axelnovaventures.com/*` → `https://axelnovaventures.com/${1}`, 301) or an nginx `server_name www…; return 301 https://axelnovaventures.com$request_uri;`.
- **Speed:** measure in PageSpeed Insights / CrUX; public pages are `swr`-cached (see [DEPLOY.md § Page caching](../global/DEPLOY.md#page-caching)).
