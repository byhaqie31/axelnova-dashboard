<script setup lang="ts">
definePageMeta({ layout: 'public' })

useHead({ title: 'Get a Quote — Axel Nova Ventures' })
const { config, configLoading, configError, loadConfig, calculate, fmtMyr } = usePricingEngine()

// Form state lives in a shared composable so the preview/success pages can read it.
const { form } = useQuoteForm()

// ── Categories ───────────────────────────────────────────────────────────────
const categories = [
  { key: 'web', label: 'Web Presence', icon: 'i-lucide-globe', packages: [
    { key: 'web_essential', name: 'Essential', tagline: 'Get online fast' },
    { key: 'web_business',  name: 'Business',  tagline: 'A proper web presence' },
    { key: 'web_premium',   name: 'Premium',   tagline: 'Built to impress internationally' },
  ]},
  { key: 'dashboard', label: 'Dashboard & Portal', icon: 'i-lucide-layout-dashboard', packages: [
    { key: 'dash_starter',    name: 'Starter',    tagline: 'Core UI, shipped clean' },
    { key: 'dash_business',   name: 'Business',   tagline: 'Role-based, data-rich' },
    { key: 'dash_enterprise', name: 'Enterprise', tagline: 'Multi-role. Mission-critical.' },
  ]},
  { key: 'design', label: 'UI/UX Design', icon: 'i-lucide-pen-tool', packages: [
    { key: 'design_audit', name: 'UX Audit', tagline: 'Find what\'s broken, fix it fast' },
    { key: 'design_figma', name: 'Figma Design', tagline: 'Full design before code' },
    { key: 'design_full',  name: 'Full Design System', tagline: 'Scale without losing consistency' },
  ]},
  { key: 'frontend', label: 'Frontend Engineering', icon: 'i-lucide-code-2', packages: [
    { key: 'frontend_components', name: 'Components', tagline: 'Build your UI library' },
    { key: 'frontend_pages',      name: 'Pages',      tagline: 'Full page implementation' },
    { key: 'frontend_full',       name: 'Full Build',  tagline: 'End-to-end frontend' },
  ]},
  { key: 'saas', label: 'SaaS & Product', icon: 'i-lucide-rocket', packages: [
    { key: 'saas_mvp_sprint', name: 'MVP Sprint', tagline: 'Validate in weeks, not months' },
    { key: 'saas_full_mvp',   name: 'Full MVP',   tagline: 'Launch-ready. Investor-ready.' },
    { key: 'not_sure',        name: 'Not sure yet', tagline: 'Tell me what you\'re building' },
  ]},
]

const currentCategory = computed(() =>
  categories.find(c => c.key === form.categoryKey),
)

// Pre-fill from /services deep links: /quote?category=web&package=web_business
const route = useRoute()
const queryCategory = typeof route.query.category === 'string' ? route.query.category : ''
const queryPackage = typeof route.query.package === 'string' ? route.query.package : ''
const matchedCategory = categories.find(c => c.key === queryCategory)
if (matchedCategory) {
  form.categoryKey = matchedCategory.key
  const matchedPackage = matchedCategory.packages.find(p => p.key === queryPackage)
  if (matchedPackage) form.packageKey = matchedPackage.key
}

// ── Live estimate ─────────────────────────────────────────────────────────────
const estimate = computed(() => {
  if (!form.packageKey || form.packageKey === 'not_sure' || !config.value) return null

  const modifiers: Record<string, boolean | number> = {}

  if (form.categoryKey === 'web') {
    if (form.pages > 5) modifiers.extra_page = form.pages
    if (form.cms) modifiers.cms = true
    if (form.bookingFlow) modifiers.booking_flow = true
    if (form.languages.length > 1) modifiers.extra_language = form.languages.length - 1
  }
  else if (form.categoryKey === 'dashboard') {
    if (form.modules > 5) modifiers.extra_module = form.modules
    if (form.realTime) modifiers.real_time_features = true
    if (form.chartsComplexity === 'advanced') modifiers.advanced_charts = true
  }

  return calculate(form.packageKey, modifiers, form.addonKeys, form.rush)
})

onMounted(() => {
  loadConfig()
})

// ── Preview navigation ────────────────────────────────────────────────────────
// This page only collects details — submission happens on `/quote/preview`
// after the user reviews the quote.
const breakdownOpen = ref(false)

const canPreview = computed(() =>
  form.name.trim().length >= 2
  && form.email.includes('@')
  && form.phone.trim().length >= 7
  && !!form.packageKey,
)

async function goToPreview() {
  if (!canPreview.value) return
  await navigateTo('/quote/preview')
}

// ── Addons ────────────────────────────────────────────────────────────────────
function toggleAddon(key: string) {
  const i = form.addonKeys.indexOf(key)
  if (i === -1) form.addonKeys.push(key)
  else form.addonKeys.splice(i, 1)
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-24 pb-32">

    <!-- Header -->
    <div class="mb-12">
      <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-accent);">Get a Quote</p>
      <h1 class="text-[36px] lg:text-[48px] font-bold tracking-tight leading-tight mb-4" style="color: var(--color-text);">
        Build something great.
      </h1>
      <p class="text-[16px] leading-relaxed max-w-xl" style="color: var(--color-text-secondary);">
        Fill in the details below and I'll email you a personalised quote within minutes.
        No commitment required.
      </p>
    </div>

    <!-- Config loading / error -->
    <div v-if="configLoading" class="text-center py-12" style="color: var(--color-text-secondary);">
      Loading pricing config…
    </div>
    <div v-else-if="configError" class="rounded-xl border px-5 py-4 mb-8" style="color: var(--color-danger); border-color: var(--color-danger);">
      {{ configError }}
    </div>

    <div v-else class="grid lg:grid-cols-[1fr_380px] gap-10 lg:gap-14 items-start">

      <!-- ── Form ─────────────────────────────────────────────────────────── -->
      <form class="space-y-10" @submit.prevent="goToPreview">

        <!-- SECTION 1: About you -->
        <section>
          <h2 class="text-[18px] font-semibold tracking-tight mb-6" style="color: var(--color-text);">
            <span class="quote-step">1</span> About you
          </h2>
          <div class="space-y-5">
            <div class="space-y-1.5">
              <label class="quote-label">Full name *</label>
              <input v-model="form.name" type="text" placeholder="John Doe" required class="contact-input"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" />
            </div>
            <div class="space-y-1.5">
              <label class="quote-label">Company / Project name</label>
              <input v-model="form.company" type="text" placeholder="Acme Sdn Bhd" class="contact-input"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" />
            </div>
            <div class="space-y-1.5">
              <label class="quote-label">Email *</label>
              <input v-model="form.email" type="email" placeholder="you@company.com" required class="contact-input"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" />
            </div>
            <div class="space-y-1.5">
              <label class="quote-label">Phone / WhatsApp *</label>
              <input v-model="form.phone" type="tel" placeholder="+60 12-345 6789" required class="contact-input"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" />
            </div>
          </div>
          <div class="mt-5 space-y-1.5">
            <label class="quote-label">How did you find me?</label>
            <div class="flex flex-wrap gap-2">
              <button v-for="s in ['Google', 'LinkedIn', 'Referral', 'GitHub', 'Other']" :key="s" type="button"
                class="text-[12px] px-3.5 py-1.5 rounded-full border transition-all"
                :style="{
                  borderColor: form.source === s ? 'var(--color-accent)' : 'var(--color-border)',
                  background: form.source === s ? 'var(--color-accent-soft)' : 'transparent',
                  color: form.source === s ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                }"
                @click="form.source = form.source === s ? '' : s">
                {{ s }}
              </button>
            </div>
          </div>
        </section>

        <!-- SECTION 2: Project type -->
        <section>
          <h2 class="text-[18px] font-semibold tracking-tight mb-6" style="color: var(--color-text);">
            <span class="quote-step">2</span> Project type
          </h2>
          <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
            <button v-for="cat in categories" :key="cat.key" type="button"
              class="quote-cat-card flex items-center gap-3 rounded-xl border px-4 py-3.5 text-left transition-all"
              :class="{ 'quote-cat-active': form.categoryKey === cat.key }"
              :style="{
                borderColor: form.categoryKey === cat.key ? 'var(--color-accent)' : 'var(--color-border)',
                background: form.categoryKey === cat.key ? 'var(--color-accent-soft)' : 'var(--color-bg-elevated)',
              }"
              @click="form.categoryKey = cat.key; form.packageKey = ''">
              <UIcon :name="cat.icon" class="size-4 shrink-0"
                :style="{ color: form.categoryKey === cat.key ? 'var(--color-accent)' : 'var(--color-text-tertiary)' }" />
              <span class="text-[13px] font-medium"
                :style="{ color: form.categoryKey === cat.key ? 'var(--color-accent)' : 'var(--color-text)' }">
                {{ cat.label }}
              </span>
            </button>
          </div>

          <!-- Package cards -->
          <Transition name="tab" mode="out-in">
            <div v-if="currentCategory" :key="form.categoryKey" class="grid sm:grid-cols-3 gap-3">
              <button v-for="pkg in currentCategory.packages" :key="pkg.key" type="button"
                class="rounded-xl border p-4 text-left transition-all"
                :style="{
                  borderColor: form.packageKey === pkg.key ? 'var(--color-accent)' : 'var(--color-border)',
                  background: form.packageKey === pkg.key ? 'var(--color-accent-soft)' : 'var(--color-bg-elevated)',
                }"
                @click="form.packageKey = pkg.key">
                <p class="text-[13px] font-semibold mb-0.5"
                  :style="{ color: form.packageKey === pkg.key ? 'var(--color-accent)' : 'var(--color-text)' }">
                  {{ pkg.name }}
                </p>
                <p class="text-[11px]" style="color: var(--color-text-tertiary);">{{ pkg.tagline }}</p>
                <p v-if="config && pkg.key !== 'not_sure' && config.base_packages[pkg.key]" class="text-[11px] mt-2 font-medium" style="color: var(--color-text-secondary);">
                  from {{ fmtMyr(config.base_packages[pkg.key]?.min ?? 0) }}
                </p>
              </button>
            </div>
          </Transition>
        </section>

        <!-- SECTION 3: Scope details (conditional) -->
        <Transition name="tab" mode="out-in">
          <section v-if="form.categoryKey && form.packageKey && form.packageKey !== 'not_sure'" :key="`scope-${form.categoryKey}`">
            <h2 class="text-[18px] font-semibold tracking-tight mb-6" style="color: var(--color-text);">
              <span class="quote-step">3</span> Scope details
            </h2>

            <!-- Web -->
            <div v-if="form.categoryKey === 'web'" class="space-y-6">
              <div class="space-y-2">
                <label class="quote-label">Number of pages: <strong style="color:var(--color-text)">{{ form.pages }}</strong></label>
                <input v-model.number="form.pages" type="range" min="1" max="20" class="quote-range w-full" />
                <div class="flex justify-between text-[11px]" style="color: var(--color-text-tertiary);">
                  <span>1 page</span><span>20 pages</span>
                </div>
              </div>
              <div class="flex flex-wrap gap-4">
                <label class="quote-toggle">
                  <input v-model="form.cms" type="checkbox" class="sr-only" />
                  <span class="quote-toggle-track" :class="{ active: form.cms }"></span>
                  <span class="text-[13px]" style="color: var(--color-text);">CMS / editable content</span>
                </label>
                <label class="quote-toggle">
                  <input v-model="form.bookingFlow" type="checkbox" class="sr-only" />
                  <span class="quote-toggle-track" :class="{ active: form.bookingFlow }"></span>
                  <span class="text-[13px]" style="color: var(--color-text);">Booking / enquiry flow</span>
                </label>
              </div>
              <div class="space-y-1.5">
                <label class="quote-label">Languages needed</label>
                <div class="flex flex-wrap gap-2">
                  <button v-for="lang in ['English', 'Bahasa Malaysia', 'Mandarin', 'Arabic']" :key="lang" type="button"
                    class="text-[12px] px-3.5 py-1.5 rounded-full border transition-all"
                    :style="{
                      borderColor: form.languages.includes(lang) ? 'var(--color-accent)' : 'var(--color-border)',
                      background: form.languages.includes(lang) ? 'var(--color-accent-soft)' : 'transparent',
                      color: form.languages.includes(lang) ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                    }"
                    @click="form.languages.includes(lang) ? form.languages.splice(form.languages.indexOf(lang),1) : form.languages.push(lang)">
                    {{ lang }}
                  </button>
                </div>
              </div>
            </div>

            <!-- Dashboard -->
            <div v-else-if="form.categoryKey === 'dashboard'" class="space-y-6">
              <div class="space-y-2">
                <label class="quote-label">Number of modules: <strong style="color:var(--color-text)">{{ form.modules }}</strong></label>
                <input v-model.number="form.modules" type="range" min="1" max="15" class="quote-range w-full" />
                <div class="flex justify-between text-[11px]" style="color: var(--color-text-tertiary);">
                  <span>1 module</span><span>15 modules</span>
                </div>
              </div>
              <div class="space-y-2">
                <label class="quote-label">User roles: <strong style="color:var(--color-text)">{{ form.userRoles }}</strong></label>
                <input v-model.number="form.userRoles" type="range" min="1" max="6" class="quote-range w-full" />
                <div class="flex justify-between text-[11px]" style="color: var(--color-text-tertiary);">
                  <span>1 role</span><span>6 roles</span>
                </div>
              </div>
              <div class="flex flex-wrap gap-4">
                <label class="quote-toggle">
                  <input v-model="form.realTime" type="checkbox" class="sr-only" />
                  <span class="quote-toggle-track" :class="{ active: form.realTime }"></span>
                  <span class="text-[13px]" style="color: var(--color-text);">Real-time updates (WebSocket)</span>
                </label>
              </div>
              <div class="space-y-1.5">
                <label class="quote-label">Charts complexity</label>
                <div class="flex flex-wrap gap-2">
                  <button v-for="opt in [{ key: 'none', label: 'None' }, { key: 'basic', label: 'Basic charts' }, { key: 'advanced', label: 'Advanced / custom' }]" :key="opt.key"
                    type="button"
                    class="text-[12px] px-3.5 py-1.5 rounded-full border transition-all"
                    :style="{
                      borderColor: form.chartsComplexity === opt.key ? 'var(--color-accent)' : 'var(--color-border)',
                      background: form.chartsComplexity === opt.key ? 'var(--color-accent-soft)' : 'transparent',
                      color: form.chartsComplexity === opt.key ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                    }"
                    @click="form.chartsComplexity = opt.key as 'none'|'basic'|'advanced'">
                    {{ opt.label }}
                  </button>
                </div>
              </div>
            </div>

            <!-- Design -->
            <div v-else-if="form.categoryKey === 'design'" class="space-y-6">
              <div class="space-y-2">
                <label class="quote-label">Number of screens: <strong style="color:var(--color-text)">{{ form.screensCount }}</strong></label>
                <input v-model.number="form.screensCount" type="range" min="1" max="40" class="quote-range w-full" />
              </div>
              <div class="flex flex-wrap gap-4">
                <label class="quote-toggle">
                  <input v-model="form.designSystem" type="checkbox" class="sr-only" />
                  <span class="quote-toggle-track" :class="{ active: form.designSystem }"></span>
                  <span class="text-[13px]" style="color: var(--color-text);">Full design system (tokens + components)</span>
                </label>
                <label class="quote-toggle">
                  <input v-model="form.prototype" type="checkbox" class="sr-only" />
                  <span class="quote-toggle-track" :class="{ active: form.prototype }"></span>
                  <span class="text-[13px]" style="color: var(--color-text);">Interactive prototype</span>
                </label>
              </div>
            </div>

            <!-- Frontend -->
            <div v-else-if="form.categoryKey === 'frontend'" class="space-y-6">
              <div class="grid sm:grid-cols-2 gap-5">
                <div class="space-y-2">
                  <label class="quote-label">Components: <strong style="color:var(--color-text)">{{ form.componentsCount }}</strong></label>
                  <input v-model.number="form.componentsCount" type="range" min="1" max="50" class="quote-range w-full" />
                </div>
                <div class="space-y-2">
                  <label class="quote-label">Pages: <strong style="color:var(--color-text)">{{ form.pagesCount }}</strong></label>
                  <input v-model.number="form.pagesCount" type="range" min="1" max="30" class="quote-range w-full" />
                </div>
              </div>
              <div class="flex flex-wrap gap-4">
                <label class="quote-toggle">
                  <input v-model="form.stateManagement" type="checkbox" class="sr-only" />
                  <span class="quote-toggle-track" :class="{ active: form.stateManagement }"></span>
                  <span class="text-[13px]" style="color: var(--color-text);">State management (Pinia)</span>
                </label>
                <label class="quote-toggle">
                  <input v-model="form.testing" type="checkbox" class="sr-only" />
                  <span class="quote-toggle-track" :class="{ active: form.testing }"></span>
                  <span class="text-[13px]" style="color: var(--color-text);">Unit tests included</span>
                </label>
              </div>
            </div>

            <!-- SaaS -->
            <div v-else-if="form.categoryKey === 'saas'" class="space-y-6">
              <div class="space-y-1.5">
                <label class="quote-label">Core features (one per line)</label>
                <textarea v-model="form.coreFeatures" rows="4" placeholder="e.g. User auth, Team management, Analytics dashboard…" class="contact-input resize-none"
                  :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" />
              </div>
              <div class="space-y-1.5">
                <label class="quote-label">Auth methods</label>
                <div class="flex flex-wrap gap-2">
                  <button v-for="m in ['Email/password', 'Magic link', 'Google SSO', 'Phone OTP']" :key="m" type="button"
                    class="text-[12px] px-3.5 py-1.5 rounded-full border transition-all"
                    :style="{
                      borderColor: form.authMethods.includes(m) ? 'var(--color-accent)' : 'var(--color-border)',
                      background: form.authMethods.includes(m) ? 'var(--color-accent-soft)' : 'transparent',
                      color: form.authMethods.includes(m) ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                    }"
                    @click="form.authMethods.includes(m) ? form.authMethods.splice(form.authMethods.indexOf(m),1) : form.authMethods.push(m)">
                    {{ m }}
                  </button>
                </div>
              </div>
              <div class="space-y-1.5">
                <label class="quote-label">Payment integration</label>
                <div class="flex flex-wrap gap-2">
                  <button v-for="p in ['None', 'Stripe', 'FPX / iPay88', 'Both']" :key="p" type="button"
                    class="text-[12px] px-3.5 py-1.5 rounded-full border transition-all"
                    :style="{
                      borderColor: form.paymentMethod === p ? 'var(--color-accent)' : 'var(--color-border)',
                      background: form.paymentMethod === p ? 'var(--color-accent-soft)' : 'transparent',
                      color: form.paymentMethod === p ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                    }"
                    @click="form.paymentMethod = p">
                    {{ p }}
                  </button>
                </div>
              </div>
              <label class="quote-toggle">
                <input v-model="form.adminPortal" type="checkbox" class="sr-only" />
                <span class="quote-toggle-track" :class="{ active: form.adminPortal }"></span>
                <span class="text-[13px]" style="color: var(--color-text);">Admin portal needed</span>
              </label>
            </div>
          </section>

          <!-- Not sure section -->
          <section v-else-if="form.packageKey === 'not_sure'" key="scope-not-sure">
            <h2 class="text-[18px] font-semibold tracking-tight mb-6" style="color: var(--color-text);">
              <span class="quote-step">3</span> Tell me what you're building
            </h2>
            <div class="space-y-1.5">
              <label class="quote-label">Describe your project or idea *</label>
              <textarea v-model="form.notSureNotes" rows="6" required
                placeholder="Tell me about your project, goals, and any constraints you have in mind…"
                class="contact-input resize-none"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" />
            </div>
          </section>
        </Transition>

        <!-- SECTION 4: Tech stack -->
        <section v-if="form.packageKey">
          <h2 class="text-[18px] font-semibold tracking-tight mb-6" style="color: var(--color-text);">
            <span class="quote-step">4</span> Tech stack preferences
          </h2>
          <div class="grid sm:grid-cols-3 gap-5">
            <div class="space-y-1.5">
              <label class="quote-label">Frontend</label>
              <div class="flex flex-wrap gap-2">
                <button v-for="t in ['Vue / Nuxt', 'React / Next', 'No preference']" :key="t" type="button"
                  class="text-[12px] px-3 py-1.5 rounded-full border transition-all"
                  :style="{
                    borderColor: form.frontendTech === t ? 'var(--color-accent)' : 'var(--color-border)',
                    background: form.frontendTech === t ? 'var(--color-accent-soft)' : 'transparent',
                    color: form.frontendTech === t ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                  }"
                  @click="form.frontendTech = t">{{ t }}</button>
              </div>
            </div>
            <div class="space-y-1.5">
              <label class="quote-label">Backend</label>
              <div class="flex flex-wrap gap-2">
                <button v-for="t in ['Laravel', 'Supabase', 'Node', 'No preference']" :key="t" type="button"
                  class="text-[12px] px-3 py-1.5 rounded-full border transition-all"
                  :style="{
                    borderColor: form.backendTech === t ? 'var(--color-accent)' : 'var(--color-border)',
                    background: form.backendTech === t ? 'var(--color-accent-soft)' : 'transparent',
                    color: form.backendTech === t ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                  }"
                  @click="form.backendTech = t">{{ t }}</button>
              </div>
            </div>
            <div class="space-y-1.5">
              <label class="quote-label">Hosting</label>
              <div class="flex flex-wrap gap-2">
                <button v-for="t in ['VPS', 'Vercel / Netlify', 'Cloudflare', 'No preference']" :key="t" type="button"
                  class="text-[12px] px-3 py-1.5 rounded-full border transition-all"
                  :style="{
                    borderColor: form.hostingPref === t ? 'var(--color-accent)' : 'var(--color-border)',
                    background: form.hostingPref === t ? 'var(--color-accent-soft)' : 'transparent',
                    color: form.hostingPref === t ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                  }"
                  @click="form.hostingPref = t">{{ t }}</button>
              </div>
            </div>
          </div>
        </section>

        <!-- SECTION 5: Add-ons -->
        <section v-if="form.packageKey && config">
          <h2 class="text-[18px] font-semibold tracking-tight mb-6" style="color: var(--color-text);">
            <span class="quote-step">5</span> Add-ons
          </h2>
          <div class="grid sm:grid-cols-2 gap-3">
            <button v-for="[key, addon] in Object.entries(config.addons)" :key="key" type="button"
              class="flex items-center justify-between rounded-xl border px-4 py-3.5 text-left transition-all"
              :style="{
                borderColor: form.addonKeys.includes(key) ? 'var(--color-accent)' : 'var(--color-border)',
                background: form.addonKeys.includes(key) ? 'var(--color-accent-soft)' : 'var(--color-bg-elevated)',
              }"
              @click="toggleAddon(key)">
              <div>
                <p class="text-[13px] font-medium"
                  :style="{ color: form.addonKeys.includes(key) ? 'var(--color-accent)' : 'var(--color-text)' }">
                  {{ addon.label }}
                </p>
              </div>
              <p class="text-[12px] font-semibold shrink-0 ml-3"
                :style="{ color: form.addonKeys.includes(key) ? 'var(--color-accent)' : 'var(--color-text-secondary)' }">
                +{{ fmtMyr(addon.amount) }}
              </p>
            </button>
          </div>
        </section>

        <!-- SECTION 6: Timeline & budget -->
        <section v-if="form.packageKey">
          <h2 class="text-[18px] font-semibold tracking-tight mb-6" style="color: var(--color-text);">
            <span class="quote-step">6</span> Timeline & budget
          </h2>
          <div class="space-y-5">
            <div class="space-y-1.5">
              <label class="quote-label">Budget range</label>
              <div class="flex flex-wrap gap-2">
                <button v-for="b in ['< RM 5k', 'RM 5k – 15k', 'RM 15k – 40k', 'RM 40k+', 'Flexible']" :key="b" type="button"
                  class="text-[12px] px-3.5 py-1.5 rounded-full border transition-all"
                  :style="{
                    borderColor: form.budgetRange === b ? 'var(--color-accent)' : 'var(--color-border)',
                    background: form.budgetRange === b ? 'var(--color-accent-soft)' : 'transparent',
                    color: form.budgetRange === b ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                  }"
                  @click="form.budgetRange = b">{{ b }}</button>
              </div>
            </div>
            <label class="quote-toggle flex items-center gap-3">
              <input v-model="form.rush" type="checkbox" class="sr-only" />
              <span class="quote-toggle-track" :class="{ active: form.rush }"></span>
              <div>
                <span class="text-[13px] font-medium" style="color: var(--color-text);">Rush delivery</span>
                <span class="text-[12px] ml-2" style="color: var(--color-text-tertiary);">(+20%, timeline reduced ~30%)</span>
              </div>
            </label>
            <div class="space-y-1.5">
              <label class="quote-label">Additional notes</label>
              <textarea v-model="form.notes" rows="4"
                placeholder="Anything else I should know? Deadlines, integrations, design references…"
                class="contact-input resize-none w-4/5"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" />
            </div>
          </div>
        </section>

        <!-- Preview CTA — actual submission lives on /quote/preview. -->
        <div v-show="form.packageKey" class="space-y-4">
          <!-- Honeypot -->
          <input type="text" name="website_url" class="hidden" tabindex="-1" autocomplete="off" />

          <button type="submit" class="btn-pill btn-pill-accent w-full justify-center"
            :disabled="!canPreview"
            :style="{ opacity: !canPreview ? '0.6' : '1', cursor: !canPreview ? 'not-allowed' : 'pointer' }">
            Preview my quote →
          </button>
          <p class="text-[11px] text-center" style="color: var(--color-text-tertiary);">
            You'll review your quote before anything is sent.
          </p>
        </div>
      </form>

      <!-- ── Sticky estimate card ───────────────────────────────────────── -->
      <div class="lg:sticky lg:top-28">
        <div class="rounded-2xl border p-6 space-y-5"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">

          <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">Live estimate</p>

          <div v-if="estimate">
            <p class="text-[32px] font-bold tracking-tight leading-none mb-1" style="color: var(--color-text);">
              {{ fmtMyr(estimate.minMyr) }}
              <span style="color: var(--color-text-tertiary);">–</span>
              {{ fmtMyr(estimate.maxMyr) }}
            </p>
            <p class="text-[13px]" style="color: var(--color-text-secondary);">
              Estimated timeline: <span class="font-medium" style="color: var(--color-text);">{{ estimate.weeks }} week{{ estimate.weeks > 1 ? 's' : '' }}</span>
            </p>
          </div>
          <div v-else class="py-2">
            <p class="text-[14px]" style="color: var(--color-text-secondary);">
              Select a package to see your estimate.
            </p>
          </div>

          <!-- Breakdown accordion -->
          <div v-if="estimate && estimate.breakdown.length > 1" class="border-t pt-4" style="border-color: var(--color-border);">
            <button class="flex items-center justify-between w-full text-left" @click="breakdownOpen = !breakdownOpen">
              <span class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Price breakdown</span>
              <UIcon :name="breakdownOpen ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'" class="size-3.5"
                style="color: var(--color-text-tertiary);" />
            </button>
            <Transition name="tab">
              <div v-if="breakdownOpen" class="mt-3 space-y-2">
                <div v-for="(line, i) in estimate.breakdown" :key="i" class="flex items-center justify-between">
                  <span class="text-[12px]" style="color: var(--color-text-secondary);">{{ line[0] }}</span>
                  <span v-if="line[1] > 0" class="text-[12px] font-medium" style="color: var(--color-text);">
                    +{{ fmtMyr(line[1]) }}
                  </span>
                </div>
              </div>
            </Transition>
          </div>

          <!-- Add-ons summary -->
          <div v-if="form.addonKeys.length && config" class="border-t pt-4 space-y-1.5" style="border-color: var(--color-border);">
            <p class="text-[11px] font-medium uppercase tracking-widest mb-2" style="color: var(--color-text-tertiary);">Add-ons selected</p>
            <div v-for="key in form.addonKeys" :key="key" class="flex items-center justify-between">
              <span class="text-[12px]" style="color: var(--color-text-secondary);">{{ config.addons[key]?.label }}</span>
              <span class="text-[12px] font-medium" style="color: var(--color-text);">+{{ fmtMyr(config.addons[key]?.amount ?? 0) }}</span>
            </div>
          </div>

          <p class="text-[11px] leading-relaxed" style="color: var(--color-text-tertiary);">
            Estimates are in MYR, excl. SST. Final price confirmed after scoping call.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.quote-step {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
  margin-right: 10px;
  background: var(--color-accent-soft);
  color: var(--color-accent);
}

.quote-label {
  display: block;
  font-size: 12px;
  font-weight: 500;
  color: var(--color-text-secondary);
}

.quote-range {
  -webkit-appearance: none;
  appearance: none;
  height: 4px;
  border-radius: 999px;
  background: var(--color-border-strong);
  outline: none;
  cursor: pointer;
}

.quote-range::-webkit-slider-thumb {
  -webkit-appearance: none;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: var(--color-accent);
  cursor: pointer;
  border: 2px solid var(--color-bg);
  box-shadow: 0 1px 4px rgba(0,0,0,0.2);
}

.quote-toggle {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
}

.quote-toggle-track {
  display: inline-flex;
  width: 36px;
  height: 20px;
  border-radius: 999px;
  background: var(--color-border-strong);
  position: relative;
  flex-shrink: 0;
  transition: background 0.15s ease;
}

.quote-toggle-track::after {
  content: '';
  position: absolute;
  top: 2px;
  left: 2px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: white;
  transition: transform 0.15s ease;
}

.quote-toggle-track.active {
  background: var(--color-accent);
}

.quote-toggle-track.active::after {
  transform: translateX(16px);
}

.tab-enter-active,
.tab-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.tab-enter-from,
.tab-leave-to {
  opacity: 0;
  transform: translateY(4px);
}

@media (prefers-reduced-motion: reduce) {
  .tab-enter-active,
  .tab-leave-active { transition: none; }
}
</style>
