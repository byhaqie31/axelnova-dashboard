import type { RouterConfig } from '@nuxt/schema'

export default <RouterConfig>{
  scrollBehavior(to, from, savedPosition) {
    // Browser back/forward — restore previous scroll position
    if (savedPosition) return savedPosition

    // Same path, only hash changed → scroll to anchor
    if (to.hash) return { el: to.hash, top: 60 }

    // Any forward navigation → start from the top, after the page transition
    return new Promise((resolve) => {
      setTimeout(() => resolve({ top: 0, left: 0 }), 260)
    })
  },
}
