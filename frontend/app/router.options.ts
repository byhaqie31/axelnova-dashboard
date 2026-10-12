import type { RouterConfig } from '@nuxt/schema'

export default <RouterConfig>{
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition
    if (to.hash) {
      if (to.path === from.path) return { el: to.hash, top: 64, behavior: 'smooth' }
      // A #hash on ANOTHER page: leave the old page where it is while it fades
      // out — the page transition lands on the hash once the new page is in
      // (app.vue onEnter, utils/hashScroll.ts).
      return false
    }
    return { top: 0, left: 0, behavior: 'instant' }
  },
}
