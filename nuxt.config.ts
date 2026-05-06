// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  modules: [
    '@nuxt/ui',
    '@vueuse/nuxt',
    '@nuxtjs/google-fonts',
  ],

  css: ['~/assets/css/main.css'],

  googleFonts: {
    families: {
      'Inter': [400, 500, 600, 700, 800],
    },
    display: 'swap',
    download: true,
  },

  app: {
    head: {
      title: 'Axel Nova Ventures',
      meta: [
        { name: 'description', content: 'UI/UX engineer with 7 years of building — fintech, SaaS, and products that need real craft.' },
        { property: 'og:title', content: 'Axel Nova Ventures' },
        { property: 'og:description', content: 'UI/UX-focused software engineer. Vue · Nuxt · Laravel · Docker · AWS.' },
      ],
      link: [{ rel: 'icon', type: 'image/svg+xml', href: '/axelnovaicon.svg' }],
    },
    pageTransition: { name: 'page', mode: 'out-in' },
  },
})
