export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.hook('page:transition:finish', () => {
    window.scrollTo({ top: 0 })
  })
})
