// Blog domain metadata — shapes shared by the admin editor (/admin/blog), the
// public pages (/blog, /blog/[slug]) and the article component. Mirrors the
// backend resources (BlogPostCardResource / PublicBlogPostResource /
// AdminBlogPostResource) and the blog_posts `sections` JSON shape. See
// docs/global/BLOG.md.

/** One element of `blog_posts.sections` — Markdown body, optional image / quote. */
export interface BlogSection {
  id: string
  heading: string
  body_md: string
  image_url: string | null
  image_alt: string | null
  quote: string | null
  quote_by: string | null
}

export type BlogFormat = 'article' | 'guide' | 'tutorial' | 'case_study' | 'opinion' | 'news'

/** The editor's Format dropdown (right rail) — mirrors BlogPost::FORMATS. */
export const blogFormatOptions: { value: BlogFormat, label: string }[] = [
  { value: 'article', label: 'Article' },
  { value: 'guide', label: 'Guide' },
  { value: 'tutorial', label: 'Tutorial' },
  { value: 'case_study', label: 'Case study' },
  { value: 'opinion', label: 'Opinion' },
  { value: 'news', label: 'News' },
]

export function blogFormatLabel(format: BlogFormat | string | null | undefined): string {
  return blogFormatOptions.find(o => o.value === format)?.label ?? 'Article'
}

export interface BlogTocItem { id: string, heading: string }

/** A section as the public API serves it — backend-rendered HTML + its TOC anchor. */
export interface BlogRenderedSection extends BlogSection {
  anchor: string
  body_html: string
}

/** Matches BlogPostCardResource. */
export interface BlogPostCard {
  slug: string
  title: string
  /** Plain text — the stored intro's **bold** / *italic* markers are stripped server-side. */
  excerpt: string
  /** Editorial format — the accent eyebrow on the page (article | guide | tutorial | case_study | opinion | news). */
  format: BlogFormat
  cover_image_url: string | null
  cover_image_alt: string | null
  category: string | null
  tags: string[]
  reading_minutes: number
  published_at: string | null
}

/** Matches PublicBlogPostResource. */
export interface BlogPostPublic extends BlogPostCard {
  /** The intro as sanitised HTML — <p>/<strong>/<em> only (BlogMarkdown::introHtml). */
  excerpt_html: string
  sections: BlogRenderedSection[]
  toc: BlogTocItem[]
  cta_heading: string | null
  cta_body: string | null
  cta_label: string | null
  cta_url: string | null
  seo_title: string | null
  seo_description: string | null
  updated_at: string | null
  related: BlogPostCard[]
}

/** Matches AdminBlogPostResource. */
export interface BlogPostAdmin {
  id: number
  slug: string
  title: string
  excerpt: string
  format: BlogFormat
  sections: BlogSection[]
  cover_image_url: string | null
  cover_image_alt: string | null
  category: string | null
  tags: string[]
  cta_heading: string | null
  cta_body: string | null
  cta_label: string | null
  cta_url: string | null
  seo_title: string | null
  seo_description: string | null
  reading_minutes: number
  status: 'draft' | 'published'
  published_at: string | null
  /** Page-view count, present on the admin list only. */
  views?: number
  created_at: string
  updated_at: string
}

/** Filter options for the admin list (AdminStatusFilter shape). */
export const blogStatusOptions = [
  { value: '', label: 'All' },
  { value: 'draft', label: 'Draft' },
  { value: 'published', label: 'Published' },
]

/**
 * The writing guide — ONE copy on the backend (config/blog.php), served by
 * GET /v1/admin/blog/guide (App\Support\BlogGuide::base). The same voice rules
 * reach Claude through the MCP connector, so they can't drift apart.
 */
export interface BlogGuide {
  voice: string[]
  structure: string[]
  /** The closing CTA a post falls back to when its own cta_* are empty. */
  cta_defaults: { heading: string, body: string, label: string, url: string }
  formats: BlogFormat[]
}

const ID_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789'

/** A fresh, empty section with a stable client-side id (the backend keeps a valid one). */
export function newSection(heading = ''): BlogSection {
  let id = 's_'
  for (let i = 0; i < 6; i++) id += ID_CHARS[Math.floor(Math.random() * ID_CHARS.length)]
  return { id, heading, body_md: '', image_url: null, image_alt: null, quote: null, quote_by: null }
}

/** The default six-part structure as placeholder sections (hook = excerpt, invitation = CTA). */
export function templateSections(): BlogSection[] {
  return ['The problem', 'Why it matters', 'A practical step', 'Another practical step', 'Key takeaway'].map(h => newSection(h))
}

/** Client-side mirror of Str::slug for the auto-slug preview; the backend value is authoritative. */
export function slugify(title: string): string {
  return title
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 110)
    .replace(/-+$/, '')
}

/** Live reading-time estimate (200 wpm, min 1) — display only; the backend recomputes on save. */
export function readingMinutes(excerpt: string, sections: Pick<BlogSection, 'body_md'>[]): number {
  const text = [excerpt, ...sections.map(s => s.body_md)].join(' ').replace(/[#*_>`[\]()!-]/g, ' ')
  const words = text.split(/\s+/).filter(Boolean).length
  return Math.max(1, Math.ceil(words / 200))
}

/**
 * Split a pasted Markdown draft into the form's shape: `# ` → title, the lines
 * before the first `## ` → introduction, each `## ` → a section (deeper
 * headings stay inside the body). An introduction longer than the 500-char
 * excerpt limit keeps its first paragraph as the excerpt and moves the rest
 * into a leading "Introduction" section.
 */
export function parseMarkdownImport(md: string): { title: string, excerpt: string, sections: BlogSection[] } {
  const lines = md.replace(/\r\n?/g, '\n').split('\n')
  let title = ''
  const intro: string[] = []
  const sections: BlogSection[] = []
  let current: BlogSection | null = null

  for (const line of lines) {
    const h1 = /^#\s+(.+)$/.exec(line)
    const h2 = /^##\s+(.+)$/.exec(line)
    if (h1 && !title && !current) {
      title = h1[1]!.trim()
      continue
    }
    if (h2) {
      current = newSection(h2[1]!.trim())
      sections.push(current)
      continue
    }
    if (current) current.body_md += `${line}\n`
    else intro.push(line)
  }
  for (const s of sections) s.body_md = s.body_md.trim()

  const introText = intro.join('\n').trim()
  let excerpt = introText
  if (introText.length > 500) {
    const [first, ...rest] = introText.split(/\n{2,}/)
    excerpt = first!.trim()
    if (rest.length) sections.unshift({ ...newSection('Introduction'), body_md: rest.join('\n\n').trim() })
  }

  return { title, excerpt, sections }
}

/** '26 September 2026' — day-first, long month, unambiguous for a Malaysian audience reading in English. */
export function fmtBlogDate(iso: string | null): string {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'long', year: 'numeric' })
}
