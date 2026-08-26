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

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  modules: [
    '@nuxt/eslint',
    '@nuxt/ui',
    '@vueuse/nuxt',
    '@nuxtjs/google-fonts',
    '@nuxtjs/sitemap',
  ],

  site: {
    url: 'https://axelnovaventures.com',
    name: 'Axel Nova Ventures',
  },

  sitemap: {
    exclude: [
      '/admin/**',
      '/portal/**',
      '/proposals/**',
      '/feedback/**',
      '/quote/success',
      '/investor/**',
    ],
  },

  css: ['~/assets/css/main.css'],

  // Server-side fetches (SSR) hit the backend via the docker-network hostname.
  // Browser fetches (CSR) hit the host loopback. Override at runtime via
  // NUXT_API_BASE (private) and NUXT_PUBLIC_API_BASE (public).
  runtimeConfig: {
    apiBase: 'http://backend:8003',
    public: {
      apiBase: 'http://localhost:8003',
    },
  },

  googleFonts: {
    families: {
      'Inter': [400, 500, 600, 700, 800], // body face
      'Outfit': [400, 500, 600],          // display face (hero headline)
    },
    display: 'swap',
    download: true,
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
    '/': { swr: 300 },
    '/about': { swr: 300 },
    '/company': { swr: 300 },
    '/contact': { swr: 300 },
    '/services': { swr: 300 },
    '/services/**': { swr: 300 },
    '/projects': { swr: 300 },
    '/projects/**': { swr: 300 },
    // Legal copy changes on the order of never.
    '/legal/**': { swr: 3600 },
  },

  app: {
    head: {
      title: 'Axel Nova Ventures',
      htmlAttrs: { lang: 'en' },
      meta: [
        { name: 'description', content: 'UI/UX engineer with 7 years of building — fintech, SaaS, and products that need real craft.' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { property: 'og:title', content: 'Axel Nova Ventures' },
        { property: 'og:description', content: 'UI/UX-focused software engineer. Vue · Nuxt · Laravel · Docker · AWS.' },
        { property: 'og:type', content: 'website' },
        { property: 'og:site_name', content: 'Axel Nova Ventures' },
        { property: 'og:locale', content: 'en_US' },
        { property: 'og:image', content: 'https://axelnovaventures.com/og-image.png' },
        { name: 'twitter:card', content: 'summary_large_image' },
        { name: 'twitter:title', content: 'Axel Nova Ventures' },
        { name: 'twitter:description', content: 'UI/UX-focused software engineer. Vue · Nuxt · Laravel · Docker · AWS.' },
        { name: 'twitter:image', content: 'https://axelnovaventures.com/og-image.png' },
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
      // `.hero-loader` on that attribute. Private mode throws on
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
