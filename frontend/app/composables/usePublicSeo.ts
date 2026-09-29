// One-call SEO block for public pages: title, meta description, the full
// Open Graph + Twitter card set (what Google snippets, WhatsApp/Facebook/
// LinkedIn link previews, and Meta's crawler all read), and a canonical URL.
// Rendered during SSR, so crawlers see it without executing JS.
//
// Usage (script setup):
//   usePublicSeo({
//     title: 'Projects — Axel Nova Ventures',
//     description: 'Selected builds …',
//     path: '/projects',
//     card: { label: 'WORK', headline: 'Selected work and products.' },
//   })
//
// `card` swaps the shared og-image.jpg for a generated per-page preview
// (components/OgImage/SiteCard.takumi.vue). `image` — a prepared picture such
// as a blog cover — wins over `card`. See docs/frontend/OG-IMAGES.md.
const SITE_URL = 'https://axelnovaventures.com'
const OG_IMAGE = `${SITE_URL}/og-image.jpg`
const OG_IMAGE_ALT = 'Axel Nova Ventures — Crafted by design. Built to last.'

export interface OgCard {
  /** Small uppercase pill, e.g. 'SERVICES'. */
  label: string
  headline: string
  subline?: string
  /** Screenshot shown in a browser frame in place of the tile art. */
  image?: string
}

/** The address pill on a generated card: the page URL while it stays short. */
export function ogFooter(path: string): string {
  const full = `axelnovaventures.com${path}`
  return full.length <= 34 ? full : 'axelnovaventures.com'
}

/** Clip copy at a word boundary — meta descriptions and card sublines. */
export function clipText(text: string | null | undefined, max: number): string {
  const flat = (text ?? '').replace(/\s+/g, ' ').trim()
  if (flat.length <= max) return flat
  const cut = flat.slice(0, max - 1)
  const end = cut.lastIndexOf(' ') > 0 ? cut.lastIndexOf(' ') : cut.length
  return `${cut.slice(0, end).replace(/[,;:.\s]+$/, '')}…`
}

export function usePublicSeo(opts: {
  title: string
  description: string
  /** Route path used for canonical + og:url, e.g. '/projects'. '' = homepage. */
  path: string
  /** Override the shared 1200×630 site card when a page has its own image. */
  image?: string
  /** Generate a per-page preview card instead of the shared site card. */
  card?: OgCard
}) {
  const url = `${SITE_URL}${opts.path}`

  useSeoMeta({
    title: opts.title,
    description: opts.description,
    ogTitle: opts.title,
    ogDescription: opts.description,
    ogUrl: url,
    twitterTitle: opts.title,
    twitterDescription: opts.description,
    twitterCard: 'summary_large_image',
  })

  if (!opts.image && opts.card) {
    defineOgImage('SiteCard', {
      label: opts.card.label,
      headline: opts.card.headline,
      subline: opts.card.subline,
      image: opts.card.image,
      footer: ogFooter(opts.path),
    }, { alt: opts.card.headline })
  }
  else {
    // og:image must be absolute — crawlers ignore relative URLs.
    const image = opts.image
      ? (opts.image.startsWith('http') ? opts.image : `${SITE_URL}${opts.image}`)
      : OG_IMAGE
    const isDefaultCard = image === OG_IMAGE
    useSeoMeta({
      ogImage: image,
      // Dimensions/alt are only known for the shared site card.
      ogImageWidth: isDefaultCard ? 1200 : undefined,
      ogImageHeight: isDefaultCard ? 630 : undefined,
      ogImageAlt: isDefaultCard ? OG_IMAGE_ALT : opts.title,
      twitterImage: image,
    })
  }

  useHead({
    link: [{ rel: 'canonical', href: url }],
  })
}
