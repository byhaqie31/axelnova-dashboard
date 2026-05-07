import gsap from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'

export default defineNuxtPlugin((nuxtApp) => {
  gsap.registerPlugin(ScrollTrigger)

  // After each navigation, recalculate ScrollTrigger positions against
  // the new page's layout now that scroll has been reset to 0.
  nuxtApp.hook('page:finish', () => {
    ScrollTrigger.refresh()
  })

  return {
    provide: { gsap, ScrollTrigger },
  }
})
