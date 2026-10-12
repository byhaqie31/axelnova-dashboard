// @ts-check
import withNuxt from './.nuxt/eslint.config.mjs'

export default withNuxt({
  rules: {
    // Nuxt file-based routing names pages/layouts by location (index.vue,
    // admin.vue) — multi-word names would fight the framework.
    'vue/multi-word-component-names': 'off',
    // ~80 pre-existing `any`s (mostly API payloads). Kept visible as warnings
    // to burn down over time; new errors still fail CI via --max-warnings
    // being unset (errors only gate).
    '@typescript-eslint/no-explicit-any': 'warn',
    // The quote builder passes one shared reactive `state` object down to
    // QuoteScopeFields, which writes its properties by contract. Only flag
    // reassigning the prop itself.
    'vue/no-mutating-props': ['error', { shallowOnly: true }],
  },
})
  // @nuxt/eslint-config ignores every folder named `public` ("**/public"),
  // meant for the static dir — which also hid app/pages/public and
  // app/components/public, the whole public site, from lint. Ignore only the
  // static root `public/`.
  .onResolved((configs) => {
    for (const config of configs) {
      if (config.ignores?.includes('**/public'))
        config.ignores = config.ignores.map(p => (p === '**/public' ? 'public' : p))
    }
  })
