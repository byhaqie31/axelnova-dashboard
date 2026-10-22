# SEO, AI answers and Discover — the discovery layer

How axelnovaventures.com is found: by search engines, by AI answer engines (ChatGPT search, Claude, Perplexity, Google AI Overviews) and by feed-driven surfaces (Google Discover's Follow, Samsung News, feed readers). This is the single doc for the area; the per-page copy rules live in [docs/frontend/SEO.md](../frontend/SEO.md) and the link-preview cards in [OG-IMAGES.md](../frontend/OG-IMAGES.md).

**Everything here raises odds. Nothing guarantees a ranking, a citation or a Discover placement** — those are probabilistic decisions made by systems we don't control. The job of this layer is to remove every technical reason to be skipped and to make the content easy to retrieve, attribute and quote. Built 2026-10-21 (Phase 1).

## 1. What's in place and where

| Concern | Where | Notes |
|---|---|---|
| Per-page title / description / canonical / Open Graph / Twitter | [`composables/usePublicSeo.ts`](../../frontend/app/composables/usePublicSeo.ts) | One call per public page. `card` → a generated per-page OG image; `image` (a blog cover) wins over `card`. The homepage sets its own via `useSeoMeta` in `pages/public/index.vue` |
| Site-wide fallbacks | [`nuxt.config.ts`](../../frontend/nuxt.config.ts) `app.head` | Description, OG defaults, **`robots: index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1`** (the large-preview directive is a Discover requirement), and the **RSS autodiscovery link** (`<link rel="alternate" type="application/rss+xml" href="…/feed.xml">`) |
| noindex surfaces | `useSeoMeta({ robots: 'noindex, nofollow' })` in the admin / team / portal layouts, the auth pages, `/quote/success`, `/feedback/{token}`, `/proposals/{slug}`, `/investor/*` | unhead treats `robots` as a single-instance meta (`StandardSingleMetaTags`), so the page-level tag **replaces** the site-wide one — exactly one `<meta name="robots">` renders, and `noindex` wins. `admin.axelnovaventures.com` adds `X-Robots-Tag: noindex, nofollow` on every response and 301s public pages to the apex ([DEPLOY.md § Admin host](./DEPLOY.md#admin-host-internal-only)) |
| Sitemap | `/sitemap.xml` via `@nuxtjs/sitemap`; dynamic entries from [`server/api/__sitemap__/urls.ts`](../../frontend/server/api/__sitemap__/urls.ts) (`/v1/blog/slugs`, `/v1/services`, `/v1/projects` — each source fails independently) | `sitemap.exclude` removes every private / noindex page. Never list a `noindex` page |
| robots.txt | [`public/robots.txt`](../../frontend/public/robots.txt) | § 4 |
| RSS feed | `/feed.xml` — [`server/routes/feed.xml.ts`](../../frontend/server/routes/feed.xml.ts) | § 2 |
| llms.txt | `/llms.txt` — [`server/routes/llms.txt.ts`](../../frontend/server/routes/llms.txt.ts) | § 3 |
| Caching | `swr: 300` route rules on every public page **and** on `/feed.xml` + `/llms.txt`; `App\Support\SiteCache` → `POST /_cache/purge` drops every cached route on a live blog / catalogue change | [DEPLOY.md § Page caching](./DEPLOY.md#page-caching). The `swr()` helper is a no-op in dev |

### The JSON-LD entity graph

Structured data is one connected graph, joined by stable `@id`s. **Never change an `@id`** — every other node points at it.

| Page | Node(s) | Joins |
|---|---|---|
| `/` | `ProfessionalService` **`/#business`** (name, phone `+60183173103`, email, Kuala Lumpur, `areaServed` Malaysia, `knowsAbout`, `sameAs`, `hasMap`), `WebSite` **`/#website`** | `WebSite.publisher → /#business` |
| `/about` | `Person` **`/about#person`** (Ahmad Baihaqie) | `worksFor → /#business` |
| `/services/{slug}` | `Service` + `BreadcrumbList` + `FAQPage` (from `enrichmentBySlug`) | `provider → /#business` |
| `/blog/{slug}` | `BlogPosting` + `BreadcrumbList` + `FAQPage` when the post has FAQs (the `faqs` field, see BLOG.md) | `author → /about#person`, `publisher → /#business`. Phase 1 added `url`, `inLanguage: en-MY`, `isAccessibleForFree`, `wordCount` (sections + excerpt, tags stripped), `timeRequired` (`PT{reading_minutes}M`) and `image` as an `ImageObject` (absolute `url` + `caption` from `cover_image_alt`) |

The homepage node's name / locality / phone must stay identical to the Google Business Profile — mismatches weaken the local signal. The same facts are repeated in `/llms.txt` (§ 3); change them together.

## 2. The RSS feed — `/feed.xml`

**What it is.** RSS 2.0, the latest **20** published posts, full text, built by [`server/routes/feed.xml.ts`](../../frontend/server/routes/feed.xml.ts) from `GET /v1/blog/feed` ([`PublicBlogController::feed`](../../backend/app/Http/Controllers/Api/V1/PublicBlogController.php) → [`BlogFeedPostResource`](../../backend/app/Http/Resources/BlogFeedPostResource.php): the article shape minus `related`, published rows only, `published_at` desc). The route only fetches and responds; every string lives in the pure builder [`server/utils/feed.ts`](../../frontend/server/utils/feed.ts) (`buildRssFeed`), unit-tested in `feed.test.ts` (escaping, CDATA guard, absolute URLs, RFC 822 dates, cap, order, empty feed, media tags).

**Channel:** title `Axel Nova Ventures — Blog`, link `/blog`, the site description, `language en-my`, `lastBuildDate` = the newest `updated_at` / `published_at` in the feed (not the render time, so it only moves when content does), `atom:link rel="self"`, `<image>` = `/axel_nova_logo.png`. Namespaces: atom, content, dc, media.

**Each item:** `title`, `link`, `guid isPermaLink="true"` (the post URL), `pubDate` (RFC 822 from `published_at`), `dc:creator` Ahmad Baihaqie, one `<category>` for the category and one per tag, `description` (the plain excerpt), `content:encoded` (CDATA: the cover as `<figure><img alt>` first when present, then each section as `<h2>` + its rendered `body_html`, with in-section images as figures and pull quotes as `<blockquote>`; **every `src`/`href` is made absolute** against the site), and for the cover `media:content url medium="image" type="image/…"` (type from the extension, JPEG by default) + `media:thumbnail`.

**Who reads it.** Feed readers (autodiscovered from any page), **Google Publisher Center** — the Discover "Follow" feature and the Google News surfaces are fed by RSS — and **Samsung News** (§ 6). All of them expect Media RSS covers and absolute links, which is why both are non-negotiable in the builder.

**Failure stance.** If the backend call fails the route returns a **valid empty channel with status 200**, never a 500 — an aggregator that sees an error may drop the subscription; an empty channel just means "nothing new". Same stance as the sitemap source.

**Caching.** `swr: 300` in production; `POST /_cache/purge` drops it with the pages, so a publish shows on the next fetch. Validate after any change: `curl -s https://axelnovaventures.com/feed.xml | xmllint --noout -` and the W3C feed validator.

## 3. llms.txt — `/llms.txt`

**What it is.** A plain-Markdown index at the site root proposed by [llmstxt.org](https://llmstxt.org): an LLM-backed agent that lands on the domain can read who the business is and where its canonical pages are in one request, instead of inferring it from HTML. Adoption by the big assistants is uneven and unannounced — it costs nothing to serve, and it is the cleanest place to state the facts we want quoted.

**How it's generated.** [`server/routes/llms.txt.ts`](../../frontend/server/routes/llms.txt.ts) fetches three backend sources in parallel — `GET /v1/services` (name, slug, `description_text`), `GET /v1/projects` (name, slug, description) and `GET /v1/blog/feed` (title, slug, excerpt) — and hands them to `buildLlmsTxt` in [`server/utils/feed.ts`](../../frontend/server/utils/feed.ts). Output: `# Axel Nova Ventures`, a `>` one-line summary, a facts paragraph, then `## Services`, `## Projects`, `## Blog`, `## Pages`, each line `- [Name](absolute url): one-line description` (collapsed whitespace, clipped at a word boundary). **Each source is independent** — a failing one drops its section, the rest still renders; an empty list also drops the section. Only Live services and active projects appear because that is what the public endpoints serve.

**Updating the static facts.** The summary, facts paragraph and the fixed `## Pages` list are constants in `buildLlmsTxt` (`LLMS_PAGES`); the channel description `SITE_DESCRIPTION` mirrors the site-wide meta description in `nuxt.config.ts`. They deliberately mirror the homepage `ProfessionalService` JSON-LD and the About `Person` node — when the phone, email, founding year, address or positioning changes, change **all three** (JSON-LD, llms.txt constants, nuxt.config description) in one PR. Cached and purged like the feed.

## 4. robots.txt — the AI-crawler policy

[`public/robots.txt`](../../frontend/public/robots.txt) keeps the original `*` group (allow everything, disallow `/admin`, `/portal`, `/proposals`, `/investor`, `/quote/preview`, `/quote/success`) and the `Sitemap:` line, and adds **explicit groups with the identical Allow/Disallow set** for: `GPTBot`, `OAI-SearchBot`, `ChatGPT-User` (OpenAI), `ClaudeBot`, `Claude-SearchBot`, `Claude-User` (Anthropic), `PerplexityBot`, `Perplexity-User`, `Google-Extended`, `Applebot-Extended`, `CCBot`.

**The trade-off, stated plainly.** Being retrievable and citable by AI answer engines is the goal of this phase — a Malaysian business owner asking an assistant "who builds booking portals in KL" should be able to get this studio named, with a link. That requires the search/answer bots (`OAI-SearchBot`, `ChatGPT-User`, `Claude-SearchBot`, `Claude-User`, `PerplexityBot`, `Perplexity-User`; Googlebot and Applebot are already covered by `*`). Some of the listed tokens also govern **training** use: `Google-Extended` (Gemini training and grounding — Google Search, Discover and AI Overviews are unaffected by it), `CCBot` (Common Crawl, the open corpus many training sets are built from), and `Applebot-Extended` (Apple's models; Siri/Spotlight search stays with `Applebot`). `GPTBot` and `ClaudeBot` are the vendors' general crawlers and serve both purposes.

**To opt out of training while keeping search/answer bots:** delete the `Google-Extended` and `CCBot` groups (and `Applebot-Extended` if wanted) — or flip their `Allow: /` to `Disallow: /`. Leave the rest. robots.txt is advisory; the serious vendors honour it, and the private surfaces are `noindex` and auth-gated regardless. Explicit groups also mean a future `*`-level change does not silently change the AI policy — edit the groups on purpose.

## 5. Google Discover — requirements and authoring rules

Discover is a feed of pages Google thinks a person will like, with no query. Eligibility is automatic for indexed pages; **selection is not controllable** — what follows removes the known disqualifiers and matches the published guidance.

**Technical (done by the site):** `max-image-preview:large` site-wide (§ 1 — Discover only shows large cards when this is allowed); `BlogPosting` with an absolute `ImageObject`, `datePublished` / `dateModified`, author and publisher entities; visible byline ("By Ahmad Baihaqie, Founder") and published / updated dates in `BlogArticle`; mobile-first pages with `swr` caching for speed; `/feed.xml` for the Follow feature.

**Authoring rules (the founder, per post):**

- **Cover image at least 1200 px wide, 16:9, a real picture** — not text-heavy, not a logo, not a tiny or square crop. Discover cards are built from the cover; a small one is the most common reason a page is never shown. Unsplash covers: use a `w=2400` (or larger) variant and check the licence. Always fill `cover_image_alt` (it becomes the `ImageObject.caption` and the feed's `alt`).
- **Headline that says what the piece delivers** — specific, no clickbait, no withholding ("you won't believe…"). The `seo_title` override exists for a shorter search title; the H1 stays honest.
- **Open with a direct answer**: 2–4 sentences under the title that answer the question the headline poses. The excerpt is that opening — it is the `description`, the feed summary and the first thing an answer engine reads.
- **Question-phrased H2s** where the section answers one ("When is a portal worth it?", "How much does a booking system cost in Malaysia?") — they map onto how people ask assistants.
- **Original material gets cited; generic advice doesn't.** A real figure from a project, a comparison table (WhatsApp vs portal, marketplace fees vs own shop), a definition in plain Malaysian-business terms, a checklist — something that doesn't exist elsewhere. Say where numbers come from.
- **Freshness honestly:** Discover favours recent pieces; update a post when facts change and the "Updated …" date moves on its own. Don't bump dates without changing content.
- Keep the voice rules in `config/blog.php` (the editor's Voice & structure panel) — they already push the same direction.
- **Use the FAQ field** (`faqs` on the post, up to 8 pairs — see [BLOG.md](BLOG.md)): 3–6 questions a reader would actually type, each answered directly in one to three plain sentences. The article renders them as a static "Frequently asked questions" block and emits `FAQPage` JSON-LD, the same shape the service pages already use — answer engines lift exactly this.

## 6. Samsung News

Samsung News (the Samsung Free / Samsung News app on Galaxy devices) syndicates publishers' RSS feeds. **`/feed.xml` is the technical prerequisite and it is in place** — Media RSS thumbnails, absolute links, full text. The rest is the owner's publisher application: apply through Samsung's news publisher programme with the feed URL, the logo and a publishing cadence; expect a review of volume and consistency before acceptance. Publish regularly first — an application with one post behind it is unlikely to pass.

## 7. Off-site checklist (owner)

Nothing below is automated; each one needs the owner's accounts. Honest participation only — never fake reviews, bought placements or astroturfed threads; a studio that sells trust can't afford to be caught manufacturing it.

1. **Google Search Console** — verify `axelnovaventures.com` (DNS TXT at Cloudflare is the durable method), submit `https://axelnovaventures.com/sitemap.xml`, URL-inspect each new post for faster first indexing, and watch the **Discover** report (it only appears once there are Discover impressions).
2. **Google Publisher Center** — add the publication, point its content section at `https://axelnovaventures.com/feed.xml`, verify via Search Console. This is what lets readers **Follow** the site in Discover / Chrome.
3. **Samsung News** — publisher application with the feed URL (§ 6).
4. **Bing Webmaster Tools** — import the site from Search Console (one click), submit the sitemap; Bing powers Copilot, DuckDuckGo and ChatGPT search's index, so this is the cheapest AI-answer win on the list.
5. **Google Business Profile** — keep the name / locality / phone identical to the JSON-LD; post weekly (a new blog post, a shipped project) with a link. Profile posts and reviews are the strongest local signal for "web developer Kuala Lumpur".
6. **Community placements (three concrete ones for a KL studio):**
   - **Lowyat.NET forum** — answer real questions in the web design / programming / small-business subforums (hosting choices, payment gateways for Malaysian shops, "should I build a portal"). Link a post only when it genuinely answers the thread, and follow the forum's signature rules.
   - **Reddit** — r/malaysia and r/MalaysianPF threads about running a business online, r/webdev for craft questions; answer fully in the comment, link the blog post as the long version.
   - **Directories** — claim listings on Clutch, GoodFirms and Sortlist with the same name, address, phone and services as the site; ask real clients for a verified review there once a project ships.

## 8. What to measure (and where)

Record a baseline on day one and compare monthly. **Never invent numbers** — if a report has no data yet, write "no data yet".

| Question | Where |
|---|---|
| Are we indexed, and which pages aren't? | Search Console → Pages (indexing) |
| Which queries and pages get impressions / clicks? | Search Console → Performance → Search results |
| Is Discover showing us? | Search Console → Performance → **Discover** (appears only with impressions) |
| Is structured data valid? | Search Console → Enhancements, plus the Rich Results Test on a post URL |
| Speed and Core Web Vitals | PageSpeed Insights (lab) and the CrUX field data it shows once there is enough traffic |
| Bing / Copilot | Bing Webmaster Tools → Search performance |
| Follows / feed health | Publisher Center (follows), `xmllint` + the W3C validator on `/feed.xml` |
| On-site reads per post | `/admin/blog` (page views per post) and `/admin/analytics` |
| AI answer inclusion | No tool reports it. Once a month, ask ChatGPT (search on), Claude, Perplexity and Google (AI Overview) 5 fixed questions — "web developer Kuala Lumpur", "booking portal Malaysia", one per service — and note whether the studio or a post is named / linked. Keep the questions and the answers in a dated note; the trend is the metric |

## 9. The first 30 days

**Week 1 — plumbing.** Deploy this phase. Verify Search Console (DNS), submit the sitemap, confirm `/feed.xml` validates on the live site, import into Bing Webmaster Tools, confirm the Business Profile matches the JSON-LD. Record the baseline (§ 8).

**Week 2 — content that can be cited.** Publish one post that contains something original (a real comparison, a price range with the reasoning, a checklist) with a 1200×675+ cover and a 2–4 sentence direct answer up top. URL-inspect it. Post it on the Business Profile.

**Week 3 — feed surfaces and listings.** Add the publication in Publisher Center with `/feed.xml`. Claim Clutch / GoodFirms / Sortlist. Answer three real Lowyat / Reddit threads properly (link only where it helps).

**Week 4 — second post and review.** Publish the second post (same rules). Run the five fixed AI questions and the Search Console / Bing reports against the baseline; note what moved and what didn't. Apply to Samsung News only if there is a steady cadence to show. Give both posts 3–6 real FAQs through the FAQ field, then decide the next step from the data, not from the plan.

Then keep the loop: one well-made post with an original asset beats four generic ones, on every surface above.
