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
    '@nuxt/ui',
    '@vueuse/nuxt',
    '@nuxtjs/google-fonts',
  ],

  css: ['~/assets/css/main.css'],

  // Server-side fetches (SSR) hit the backend via the docker-network hostname.
  // Browser fetches (CSR) hit the host loopback. Override at runtime via
  // NUXT_API_BASE (private) and NUXT_PUBLIC_API_BASE (public).
  runtimeConfig: {
    apiBase: 'http://backend:8003',
    public: {
      apiBase: 'http://localhost:8000',
    },
  },

  googleFonts: {
    families: {
      'Inter': [400, 500, 600, 700, 800],
    },
    display: 'swap',
    download: true,
  },

  hooks: {
    'pages:extend': stripPublicPrefix,
  },

  app: {
    head: {
      title: 'Axel Nova Ventures',
      meta: [
        { name: 'description', content: 'UI/UX engineer with 7 years of building — fintech, SaaS, and products that need real craft.' },
        { property: 'og:title', content: 'Axel Nova Ventures' },
        { property: 'og:description', content: 'UI/UX-focused software engineer. Vue · Nuxt · Laravel · Docker · AWS.' },
      ],
      link: [
        // Modern browsers — sharpest at any size.
        { rel: 'icon', type: 'image/svg+xml', href: '/axelnovaicon.svg' },
        // Fallback for browsers that don't render SVG favicons.
        { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' },
        // iOS home-screen icon.
        { rel: 'apple-touch-icon', href: '/axel_nova_favicon.png' },
      ],
    },
    pageTransition: { name: 'page', mode: 'out-in' },
  },
})
