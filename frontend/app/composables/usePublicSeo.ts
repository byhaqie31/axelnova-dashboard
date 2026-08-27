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
//   })
const SITE_URL = 'https://axelnovaventures.com'
const OG_IMAGE = `${SITE_URL}/og-image.jpg`
const OG_IMAGE_ALT = 'Axel Nova Ventures — Crafted by design. Built to last.'

export function usePublicSeo(opts: {
  title: string
  description: string
  /** Route path used for canonical + og:url, e.g. '/projects'. '' = homepage. */
  path: string
  /** Override the shared 1200×630 site card when a page has its own image. */
  image?: string
}) {
  const url = `${SITE_URL}${opts.path}`
  // og:image must be absolute — crawlers ignore relative URLs.
  const image = opts.image
    ? (opts.image.startsWith('http') ? opts.image : `${SITE_URL}${opts.image}`)
    : OG_IMAGE
  const isDefaultCard = image === OG_IMAGE

  useSeoMeta({
    title: opts.title,
    description: opts.description,
    ogTitle: opts.title,
    ogDescription: opts.description,
    ogImage: image,
    // Dimensions/alt are only known for the shared site card.
    ogImageWidth: isDefaultCard ? 1200 : undefined,
    ogImageHeight: isDefaultCard ? 630 : undefined,
    ogImageAlt: isDefaultCard ? OG_IMAGE_ALT : opts.title,
    ogUrl: url,
    twitterTitle: opts.title,
    twitterDescription: opts.description,
    twitterImage: image,
    twitterCard: 'summary_large_image',
  })

  useHead({
    link: [{ rel: 'canonical', href: url }],
  })
}
