// https://nuxt.com/docs/api/configuration/nuxt-config
import type { NuxtPage } from 'nuxt/schema'

// Strip "/public" from URLs of pages under pages/public/. Lets us mirror
// the admin/portal folder structure without changing public-facing URLs.
function stripPublicPrefix(list: NuxtPage[]): void {
  for (const r of list) {
    if (r.path === '/public') r.path = '/'
    else if (r.path.startsWith('/public/')) r.path = r.path.slice(7)
    if (r.children && r.children.length) stripPublicPrefix(r.children)
  }
}

// Public-page `swr` caching is a production behaviour. Under `nuxt dev` Nitro
// persists the cached HTML on disk (.nuxt/cache/nitro/routes) and keeps
// serving it across restarts, so a template edit would stay invisible for up
// to five minutes — the rules are only emitted for builds.
const isDev = process.env.NODE_ENV !== 'production'
const swr = (seconds: number) => (isDev ? {} : { swr: seconds })

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  modules: [
    '@nuxt/eslint',
    '@nuxt/ui',
    '@vueuse/nuxt',
    '@nuxt/fonts',
    '@nuxtjs/sitemap',
    'nuxt-og-image',
  ],

  site: {
    url: 'https://axelnovaventures.com',
    name: 'Axel Nova Ventures',
  },

  sitemap: {
    // Published blog posts, admin-managed service categories and projects come
    // from the backend (server/api/__sitemap__/urls.ts).
    sources: ['/api/__sitemap__/urls'],
    exclude: [
      '/admin/**',
      '/portal/**',
      '/proposals/**',
      '/feedback/**',
      '/quote/success',
      '/investor/**',
      // Signed-in workspaces — noindex pages, so listing them only earns
      // "Submitted URL marked noindex" errors in Search Console. The public
      // /partners landing page and /partners/refer stay in.
      '/team',
      '/team/**',
      '/partners/login',
      '/partners/forgot',
      '/partners/home',
      '/partners/profile',
      '/partners/documents',
      '/partners/earnings',
      '/partners/referrals',
      '/partners/reports',
    ],
  },

  css: ['~/assets/css/main.css'],

  // Server-side fetches (SSR) hit the backend via the docker-network hostname.
  // Browser fetches (CSR) hit the host loopback. Override at runtime via
  // NUXT_API_BASE (private) and NUXT_PUBLIC_API_BASE (public).
  runtimeConfig: {
    apiBase: 'http://backend:8003',
    // Shared secret for POST /_cache/purge (server/routes/_cache/purge.post.ts),
    // set via NUXT_CACHE_PURGE_TOKEN. Empty → the route 404s (dev, CI).
    cachePurgeToken: '',
    public: {
      apiBase: 'http://localhost:8003',
    },
  },

  // Self-hosted Google fonts (downloaded at build, served from /_fonts).
  // `global: true` registers the @font-face rules everywhere — the OG image
  // renderer only sees fonts declared this way (see docs/frontend/OG-IMAGES.md).
  fonts: {
    families: [
      { name: 'Inter', weights: [400, 500, 600, 700, 800], global: true },  // body face
      { name: 'Outfit', weights: [400, 500, 600], global: true },           // display face (hero headline)
      // Apple system faces in the --font-* fallback stacks: never download.
      { name: 'SF Pro Display', provider: 'none' },
      { name: 'SF Pro Text', provider: 'none' },
    ],
    defaults: { styles: ['normal'], subsets: ['latin'] },
  },

  // Generated link-preview cards (components/OgImage/*.takumi.vue), rendered
  // on first request and cached. NUXT_OG_IMAGE_SECRET must be set in
  // production so signed image URLs survive rebuilds.
  ogImage: {
    defaults: { width: 1200, height: 630 },
  },

  hooks: {
    'pages:extend': stripPublicPrefix,
  },

  // Baseline security headers on every SSR/asset response. SAMEORIGIN (not
  // DENY) so on-site previews of our own pages keep working; the API sets its
  // own headers via backend middleware.
  routeRules: {
    '/**': {
      headers: {
        'X-Frame-Options': 'SAMEORIGIN',
        'X-Content-Type-Options': 'nosniff',
        'Referrer-Policy': 'strict-origin-when-cross-origin',
      },
    },
    // Task 9: the single-page portal became a multi-page portal — keep old
    // bookmarks/emails pointing at /partners/portal working.
    '/partners/portal': { redirect: { to: '/partners/home', statusCode: 301 } },
    // Team "Payslips" was renamed "Payments" — keep old links + the home tile's
    // bookmarks working.
    '/team/payslips': { redirect: { to: '/team/payments', statusCode: 301 } },

    // Public marketing pages render identically for every visitor, so a cold
    // arrival (a Google click, which is ALWAYS cold) should never pay for the
    // SSR round-trip. `swr` serves the cached HTML immediately and revalidates
    // in the background. The homepage matters most: it `await`s the projects
    // API during SSR, so without this every first-time visitor waits on the
    // backend before seeing anything.
    //
    // Deliberately NOT listed — do not add them:
    //   /admin, /portal, /team, /partners  — authenticated, HTML is per-user
    //   /feedback/**, /proposals/**        — token/client-scoped, per-recipient
    //   /quote/**                          — form + live pricing config
    // Caching any of those would serve one visitor's page to another.
    // NOTE: do NOT add `ssr: false` for /admin/** yet, tempting as it is (the
    // admin SSR shell is data-free — token in localStorage, fetches in
    // onMounted). @nuxt/ui 4.9's colors plugin crashes on hydration of any
    // non-server-rendered page (`injectHead().hooks.hookOnce` — unhead v2 API
    // against nuxt 4.5's unhead v3), 500-ing every hard refresh inside /admin.
    // Revisit after upgrading @nuxt/ui to >= 4.11 (unhead v3 compatible).
    // `swr()` is a no-op in dev (see the helper above the config).

    '/': swr(300),
    '/about': swr(300),
    '/company': swr(300),
    '/contact': swr(300),
    '/services': swr(300),
    '/services/**': swr(300),
    '/projects': swr(300),
    '/projects/**': swr(300),
    '/blog': swr(300),
    '/blog/**': swr(300),
    // Legal copy changes on the order of never.
    '/legal/**': swr(3600),
  },

  app: {
    head: {
      title: 'Axel Nova Ventures',
      htmlAttrs: { lang: 'en' },
      // Site-wide FALLBACK meta only — every indexable public page sets its own
      // title/description via usePublicSeo() (unique copy per page beats one
      // shared description for rankings). Keep this in sync with the studio
      // positioning, and note the OG image is og-image.jpg (no .png exists).
      meta: [
        { name: 'description', content: 'Axel Nova Ventures designs and builds websites, booking portals and custom business systems in Kuala Lumpur for businesses across Malaysia.' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { property: 'og:title', content: 'Axel Nova Ventures' },
        { property: 'og:description', content: 'Axel Nova Ventures designs and builds websites, booking portals and custom business systems in Kuala Lumpur for businesses across Malaysia.' },
        { property: 'og:type', content: 'website' },
        { property: 'og:site_name', content: 'Axel Nova Ventures' },
        { property: 'og:locale', content: 'en_MY' },
        { property: 'og:image', content: 'https://axelnovaventures.com/og-image.jpg' },
        { name: 'twitter:card', content: 'summary_large_image' },
        { name: 'twitter:title', content: 'Axel Nova Ventures' },
        { name: 'twitter:description', content: 'Axel Nova Ventures designs and builds websites, booking portals and custom business systems in Kuala Lumpur for businesses across Malaysia.' },
        { name: 'twitter:image', content: 'https://axelnovaventures.com/og-image.jpg' },
      ],
      link: [
        { rel: 'icon', type: 'image/png', sizes: '96x96', href: '/favicon/favicon-96x96.png' },
        { rel: 'icon', type: 'image/x-icon', href: '/favicon/favicon.ico' },
        { rel: 'apple-touch-icon', sizes: '180x180', href: '/favicon/apple-touch-icon.png' },
        { rel: 'manifest', href: '/favicon/site.webmanifest' },
      ],
      // The homepage intro loader is rendered during SSR so it covers the page
      // from the very first paint — a client-only overlay lets the hero paint
      // first and then slams over it. This runs before paint (same trick as the
      // `.dark` class) and stamps the cases that must NEVER see a loader:
      // repeat visits this session, and reduced motion. `main.css` hides
      // `.hero-loader` on that attribute, and HeroEpoch's fullscreen
      // `.hero-boot` pose is gated on it too, so a same-session reload paints
      // the settled home view. Private mode throws on
      // sessionStorage — treat that as "seen", matching HeroEpoch's default.
      script: [
        {
          tagPosition: 'head',
          innerHTML: `(function(){try{if(sessionStorage.getItem('axn-hero-intro-seen')||matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.setAttribute('data-intro-seen','')}catch(e){document.documentElement.setAttribute('data-intro-seen','')}})()`,
        },
      ],
      // Without JS the overlay would never be torn down — never show it.
      noscript: [
        { tagPosition: 'head', innerHTML: '<style>.hero-loader{display:none}</style>' },
      ],
    },
    // Page transitions are GSAP-driven via JS hooks on <NuxtPage> in app.vue.
  },
})
