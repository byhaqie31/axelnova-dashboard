// Pure builders for the two discovery files the server serves from backend
// data: the RSS 2.0 feed (/feed.xml — server/routes/feed.xml.ts) and the
// llms.txt summary (/llms.txt — server/routes/llms.txt.ts). The routes only
// fetch and respond; everything string-shaped lives here so it can be unit
// tested in plain node (feed.test.ts). See docs/global/SEO-AND-DISCOVERY.md.

/** The backend's GET /v1/blog/feed shape (BlogFeedPostResource) — what the feed renders. */
export interface FeedSection {
  heading: string
  body_html: string
  image_url?: string | null
  image_alt?: string | null
  quote?: string | null
  quote_by?: string | null
}

export interface FeedPost {
  slug: string
  title: string
  /** Plain text — the backend strips the intro's Markdown markers. */
  excerpt: string
  cover_image_url: string | null
  cover_image_alt: string | null
  category: string | null
  tags: string[]
  published_at: string | null
  updated_at?: string | null
  sections: FeedSection[]
}

export interface FeedOptions {
  /** The public site origin, no trailing slash. Every URL in the feed is absolute against it. */
  siteUrl: string
  /** Channel description — the site-wide meta description. */
  description: string
  /** lastBuildDate fallback for an empty feed (injectable for tests). */
  now?: Date
}

/** Newest 20 — what the backend serves; the cap is repeated here so a longer payload can't bloat the feed. */
export const FEED_ITEM_LIMIT = 20

export const FEED_TITLE = 'Axel Nova Ventures — Blog'
export const FEED_AUTHOR = 'Ahmad Baihaqie'

/**
 * Mirrors the site-wide `description` meta in nuxt.config.ts (app.head.meta).
 * A Nitro route can't import the Nuxt config, so the sentence is repeated —
 * keep the two in step when the positioning changes.
 */
export const SITE_DESCRIPTION = 'Axel Nova Ventures designs and builds websites, booking portals and custom business systems in Kuala Lumpur for businesses across Malaysia.'

// XML 1.0 forbids most C0 control characters even when escaped; a stray one
// (pasted from a PDF, say) would make the whole feed unparseable.
// eslint-disable-next-line no-control-regex -- matching control characters is the point: they are stripped
const XML_CONTROL = /[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g

/** Escape text for an XML text node or attribute value. */
export function escapeXml(text: string | null | undefined): string {
  return (text ?? '')
    .replace(XML_CONTROL, '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

/** Wrap HTML in a CDATA section; an embedded `]]>` is split so it cannot end the section early. */
export function cdata(html: string): string {
  return `<![CDATA[${html.replace(XML_CONTROL, '').replace(/\]\]>/g, ']]]]><![CDATA[>')}]]>`
}

/** RSS 2.0 dates are RFC 822 — `toUTCString()` is exactly that form ("Sat, 26 Sep 2026 01:04:03 GMT"). */
export function rfc822(iso: string | Date): string {
  return new Date(iso).toUTCString()
}

/**
 * Make a URL absolute against the site. Feed readers and news aggregators
 * display items away from the site, so a root-relative `/previews/x.jpg` or
 * `/services/booking-portal` would break there. Fragments, mailto:/tel: and
 * already-absolute URLs pass through; protocol-relative ones get https.
 */
export function absolutize(url: string, siteUrl: string): string {
  const u = url.trim()
  if (u === '' || u.startsWith('#') || /^[a-z][a-z0-9+.-]*:/i.test(u)) return u
  if (u.startsWith('//')) return `https:${u}`
  return `${siteUrl.replace(/\/+$/, '')}${u.startsWith('/') ? '' : '/'}${u}`
}

/** Rewrite every src/href attribute in rendered HTML to an absolute URL. */
export function absolutizeHtml(html: string, siteUrl: string): string {
  return html.replace(/\b(src|href)=(["'])([^"']*)\2/g, (_m, attr: string, q: string, value: string) =>
    `${attr}=${q}${absolutize(value, siteUrl)}${q}`)
}

const IMAGE_TYPES: Record<string, string> = {
  png: 'image/png',
  webp: 'image/webp',
  gif: 'image/gif',
  svg: 'image/svg+xml',
  avif: 'image/avif',
  jpg: 'image/jpeg',
  jpeg: 'image/jpeg',
}

/** MIME type from the URL's extension (query string ignored); JPEG when unknown — covers are photos. */
export function imageMimeType(url: string): string {
  const path = url.split(/[?#]/)[0] ?? ''
  const ext = path.slice(path.lastIndexOf('.') + 1).toLowerCase()
  return (path.includes('.') && IMAGE_TYPES[ext]) || 'image/jpeg'
}

function figure(src: string, alt: string | null | undefined, siteUrl: string): string {
  return `<figure><img src="${escapeXml(absolutize(src, siteUrl))}" alt="${escapeXml(alt ?? '')}"></figure>`
}

/** The full article as HTML for content:encoded — the same order BlogArticle renders. */
function articleHtml(post: FeedPost, siteUrl: string): string {
  const parts: string[] = []
  if (post.cover_image_url) parts.push(figure(post.cover_image_url, post.cover_image_alt, siteUrl))
  for (const s of post.sections) {
    parts.push(`<h2>${escapeXml(s.heading)}</h2>`)
    if (s.image_url) parts.push(figure(s.image_url, s.image_alt, siteUrl))
    parts.push(absolutizeHtml(s.body_html, siteUrl))
    if (s.quote) {
      parts.push(`<blockquote><p>${escapeXml(s.quote)}</p>${s.quote_by ? `<cite>${escapeXml(s.quote_by)}</cite>` : ''}</blockquote>`)
    }
  }
  return parts.join('\n')
}

function itemXml(post: FeedPost, siteUrl: string): string {
  const url = `${siteUrl}/blog/${post.slug}`
  const categories = [post.category, ...post.tags].filter((c): c is string => !!c && c.trim() !== '')
  const lines = [
    '<item>',
    `<title>${escapeXml(post.title)}</title>`,
    `<link>${escapeXml(url)}</link>`,
    `<guid isPermaLink="true">${escapeXml(url)}</guid>`,
    ...(post.published_at ? [`<pubDate>${rfc822(post.published_at)}</pubDate>`] : []),
    `<dc:creator>${escapeXml(FEED_AUTHOR)}</dc:creator>`,
    ...categories.map(c => `<category>${escapeXml(c)}</category>`),
    `<description>${escapeXml(post.excerpt)}</description>`,
    `<content:encoded>${cdata(articleHtml(post, siteUrl))}</content:encoded>`,
  ]
  if (post.cover_image_url) {
    // Media RSS is what Google Discover's Follow feature and Samsung News read
    // for the card image; the thumbnail is the same picture (no separate crop).
    const cover = escapeXml(absolutize(post.cover_image_url, siteUrl))
    lines.push(`<media:content url="${cover}" medium="image" type="${imageMimeType(post.cover_image_url)}"/>`)
    lines.push(`<media:thumbnail url="${cover}"/>`)
  }
  lines.push('</item>')
  return lines.join('\n')
}

/** The latest change across the items — a stable lastBuildDate that only moves when the content does. */
function lastBuildDate(posts: FeedPost[], now: Date): string {
  let latest = 0
  for (const p of posts) {
    for (const iso of [p.updated_at, p.published_at]) {
      const t = iso ? new Date(iso).getTime() : Number.NaN
      if (!Number.isNaN(t) && t > latest) latest = t
    }
  }
  return rfc822(latest ? new Date(latest) : now)
}

/**
 * RSS 2.0 with the Atom self link, Dublin Core creator, content:encoded full
 * text and Media RSS covers. `posts` must already be newest-first (the backend
 * orders them) — the order is kept, only the count is capped. An empty list
 * still yields a valid channel, so a backend outage never breaks subscribers.
 */
export function buildRssFeed(posts: FeedPost[], opts: FeedOptions): string {
  const siteUrl = opts.siteUrl.replace(/\/+$/, '')
  const items = posts.slice(0, FEED_ITEM_LIMIT)
  const blogUrl = `${siteUrl}/blog`

  return [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:media="http://search.yahoo.com/mrss/">',
    '<channel>',
    `<title>${escapeXml(FEED_TITLE)}</title>`,
    `<link>${escapeXml(blogUrl)}</link>`,
    `<description>${escapeXml(opts.description)}</description>`,
    '<language>en-my</language>',
    `<lastBuildDate>${lastBuildDate(items, opts.now ?? new Date())}</lastBuildDate>`,
    `<atom:link href="${escapeXml(`${siteUrl}/feed.xml`)}" rel="self" type="application/rss+xml"/>`,
    `<image><url>${escapeXml(`${siteUrl}/axel_nova_logo.png`)}</url><title>${escapeXml(FEED_TITLE)}</title><link>${escapeXml(blogUrl)}</link></image>`,
    ...items.map(p => itemXml(p, siteUrl)),
    '</channel>',
    '</rss>',
    '',
  ].join('\n')
}

// ---------------------------------------------------------------------------
// llms.txt — https://llmstxt.org. A Markdown index an LLM-backed agent can read
// in one go: who the studio is, then the live services, projects and posts as
// `- [Name](url): one line` entries.

export interface LlmsEntry {
  slug: string
  name: string
  description: string
}

export interface LlmsPostEntry {
  slug: string
  title: string
  excerpt: string
}

/** Each source is independent — `null` means its fetch failed and the section is dropped. */
export interface LlmsSources {
  services: LlmsEntry[] | null
  projects: LlmsEntry[] | null
  posts: LlmsPostEntry[] | null
}

/** Collapse whitespace and clip at a word boundary — one tidy line per entry. */
export function oneLine(text: string | null | undefined, max = 180): string {
  const flat = (text ?? '').replace(/\s+/g, ' ').trim()
  if (flat.length <= max) return flat
  const cut = flat.slice(0, max - 1)
  const end = cut.lastIndexOf(' ') > 0 ? cut.lastIndexOf(' ') : cut.length
  return `${cut.slice(0, end).replace(/[,;:.\s]+$/, '')}…`
}

/** The fixed site map at the bottom of llms.txt — the public pages worth sending an agent to. */
const LLMS_PAGES: { name: string, path: string, description: string }[] = [
  { name: 'About', path: '/about', description: 'Ahmad Baihaqie, the founder — background and how the studio works.' },
  { name: 'Company', path: '/company', description: 'The studio: what Axel Nova Ventures is, how it is run and who it is for.' },
  { name: 'Services', path: '/services', description: 'Every service with packages, pricing guidance and a live estimator.' },
  { name: 'Projects', path: '/projects', description: 'Selected work and products the studio has built.' },
  { name: 'Collaborations', path: '/collaborations', description: 'Studio partners the team co-builds and subcontracts with.' },
  { name: 'Partners', path: '/partners', description: 'The referral programme — earn a commission for introducing a client.' },
  { name: 'Blog', path: '/blog', description: 'Articles on UI/UX, custom systems and everyday tech decisions for Malaysian businesses.' },
  { name: 'Support', path: '/support', description: 'Support the studio\'s work (DuitNow / bank transfer).' },
  { name: 'Contact', path: '/contact', description: 'Send a message — questions, partnerships, anything that is not yet a project.' },
  { name: 'Quote', path: '/quote', description: 'Start a project: describe what you need and get a priced proposal.' },
]

function entryLine(name: string, url: string, description: string): string {
  const desc = oneLine(description)
  return `- [${oneLine(name, 120)}](${url})${desc ? `: ${desc}` : ''}`
}

function section(title: string, lines: string[]): string[] {
  return lines.length ? [`## ${title}`, '', ...lines, ''] : []
}

/**
 * The static facts here mirror the ProfessionalService JSON-LD on the homepage
 * (pages/public/index.vue) and the About page's Person node — change them
 * together so search engines and LLMs read one consistent story.
 */
export function buildLlmsTxt(sources: LlmsSources, opts: { siteUrl: string }): string {
  const siteUrl = opts.siteUrl.replace(/\/+$/, '')
  const abs = (path: string) => `${siteUrl}${path}`

  const services = (sources.services ?? []).map(s => entryLine(s.name, abs(`/services/${s.slug}`), s.description))
  const projects = (sources.projects ?? []).map(p => entryLine(p.name, abs(`/projects/${p.slug}`), p.description))
  const posts = (sources.posts ?? []).map(p => entryLine(p.title, abs(`/blog/${p.slug}`), p.excerpt))
  const pages = LLMS_PAGES.map(p => entryLine(p.name, abs(p.path), p.description))

  return [
    '# Axel Nova Ventures',
    '',
    '> Kuala Lumpur web design and development studio that builds websites, booking portals and custom business systems for businesses across Malaysia.',
    '',
    `Axel Nova Ventures is a web design and development studio in Kuala Lumpur, Malaysia, founded in 2026 by Ahmad Baihaqie (founder and software engineer). It designs and builds marketing websites, booking and customer portals, e-commerce with payment flows, and custom business systems (admin panels, client portals, internal tools) for small and medium businesses across Malaysia, and offers UI/UX redesigns of existing apps. Contact: baihaqie@axelnova.tech, +60 18-317 3103, ${siteUrl}. To start a project, use the quote form at ${abs('/quote')} or the contact page at ${abs('/contact')}.`,
    '',
    ...section('Services', services),
    ...section('Projects', projects),
    ...section('Blog', posts),
    ...section('Pages', pages),
  ].join('\n')
}
