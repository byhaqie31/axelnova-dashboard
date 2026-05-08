<script setup lang="ts">
definePageMeta({ layout: 'public' })

const runtimeConfig = useRuntimeConfig()

useHead({ title: 'Review your quote — Axel Nova Ventures' })

const { config, configLoading, configError, loadConfig, calculate, fmtMyr, formatEta } = usePricingEngine()
const { form, resetForm, hasMinimumData } = useQuoteForm()

// ── Guard: bounce empty visitors back to the form ────────────────────────────
// useState resets on hard refresh, so anyone landing here without filling out the
// form (e.g. opening /quote/preview directly) shouldn't see a half-baked invoice.
onMounted(() => {
  loadConfig()
  if (!hasMinimumData()) navigateTo('/quote', { replace: true })
})

// ── Mirrors of the data shape the form page uses ─────────────────────────────
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

const currentCategory = computed(() => categories.find(c => c.key === form.categoryKey))
const currentPackage = computed(() => {
  if (!currentCategory.value || !form.packageKey) return null
  return currentCategory.value.packages.find(p => p.key === form.packageKey) ?? null
})

// ── Live estimate (re-derived from shared form state) ────────────────────────
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

// ── Invoice metadata ─────────────────────────────────────────────────────────
const todayDate = computed(() =>
  new Date().toLocaleDateString('en-GB', { year: 'numeric', month: 'long', day: 'numeric' }),
)
const validUntilDate = computed(() => {
  const d = new Date()
  d.setDate(d.getDate() + 30)
  return d.toLocaleDateString('en-GB', { year: 'numeric', month: 'long', day: 'numeric' })
})

// Human-readable scope summary, branched by category — drives the "Scope" invoice section.
const scopeSummary = computed(() => {
  const items: { label: string; value: string }[] = []

  if (form.categoryKey === 'web') {
    items.push({ label: 'Number of pages', value: String(form.pages) })
    if (form.cms) items.push({ label: 'CMS / editable content', value: 'Yes' })
    if (form.bookingFlow) items.push({ label: 'Booking / enquiry flow', value: 'Yes' })
    if (form.languages.length) items.push({ label: 'Languages', value: form.languages.join(', ') })
  }
  else if (form.categoryKey === 'dashboard') {
    items.push({ label: 'Number of modules', value: String(form.modules) })
    items.push({ label: 'User roles', value: String(form.userRoles) })
    if (form.realTime) items.push({ label: 'Real-time updates (WebSocket)', value: 'Yes' })
    const chartLabel = form.chartsComplexity === 'none'
      ? 'None'
      : form.chartsComplexity === 'basic' ? 'Basic charts' : 'Advanced / custom'
    items.push({ label: 'Charts complexity', value: chartLabel })
  }
  else if (form.categoryKey === 'design') {
    items.push({ label: 'Number of screens', value: String(form.screensCount) })
    if (form.designSystem) items.push({ label: 'Full design system', value: 'Yes' })
    if (form.prototype) items.push({ label: 'Interactive prototype', value: 'Yes' })
  }
  else if (form.categoryKey === 'frontend') {
    items.push({ label: 'Components', value: String(form.componentsCount) })
    items.push({ label: 'Pages', value: String(form.pagesCount) })
    if (form.stateManagement) items.push({ label: 'State management (Pinia)', value: 'Yes' })
    if (form.testing) items.push({ label: 'Unit tests included', value: 'Yes' })
  }
  else if (form.categoryKey === 'saas') {
    if (form.coreFeatures.trim()) items.push({ label: 'Core features', value: form.coreFeatures.trim() })
    if (form.authMethods.length) items.push({ label: 'Auth methods', value: form.authMethods.join(', ') })
    if (form.paymentMethod) items.push({ label: 'Payment integration', value: form.paymentMethod })
    if (form.adminPortal) items.push({ label: 'Admin portal', value: 'Yes' })
  }

  return items
})

// ── Submit (moved from quote.vue — same payload shape) ──────────────────────
const loading = ref(false)
const error = ref('')

const canSubmit = computed(() => hasMinimumData())

async function handleSubmit() {
  if (!canSubmit.value) return
  loading.value = true
  error.value = ''

  try {
    const payload: Record<string, unknown> = {
      name: form.name,
      email: form.email,
      phone: form.phone,
      company: form.company || undefined,
      package_key: form.packageKey,
      rush: form.rush,
      addon_keys: form.addonKeys,
      form_payload: {
        source: form.source,
        notes: form.notes,
        budget_range: form.budgetRange,
        frontend_tech: form.frontendTech,
        backend_tech: form.backendTech,
        hosting_pref: form.hostingPref,
        pages: form.pages,
        languages: form.languages,
        cms: form.cms,
        booking_flow: form.bookingFlow,
        modules: form.modules,
        user_roles: form.userRoles,
        real_time: form.realTime,
        charts_complexity: form.chartsComplexity,
        screens_count: form.screensCount,
        design_system: form.designSystem,
        prototype: form.prototype,
        components_count: form.componentsCount,
        pages_count: form.pagesCount,
        state_management: form.stateManagement,
        testing: form.testing,
        core_features: form.coreFeatures,
        auth_methods: form.authMethods,
        payment_method: form.paymentMethod,
        admin_portal: form.adminPortal,
        not_sure_notes: form.notSureNotes,
      },
      modifiers: {} as Record<string, boolean | number>,
    }

    const mods = payload.modifiers as Record<string, boolean | number>
    if (form.categoryKey === 'web') {
      if (form.pages > 5) mods.extra_page = form.pages
      if (form.cms) mods.cms = true
      if (form.bookingFlow) mods.booking_flow = true
      if (form.languages.length > 1) mods.extra_language = form.languages.length - 1
    }
    else if (form.categoryKey === 'dashboard') {
      if (form.modules > 5) mods.extra_module = form.modules
      if (form.realTime) mods.real_time_features = true
      if (form.chartsComplexity === 'advanced') mods.advanced_charts = true
    }

    const res = await $fetch<{ data: { reference_code: string; valid_until: string } }>(
      `${runtimeConfig.public.apiBase}/api/v1/quote-requests`,
      { method: 'POST', body: payload },
    )

    await navigateTo(`/quote/success?ref=${res.data.reference_code}&until=${res.data.valid_until}`)
  }
  catch (e: any) {
    console.error('[quote submit] failed:', e)
    const errorList = e?.data?.errors ? Object.values(e.data.errors).flat().join(' ') : ''
    error.value = errorList || e?.data?.message || 'Something went wrong. Please try again.'
  }
  finally {
    loading.value = false
  }
}

function backToEdit() {
  navigateTo('/quote')
}

function startAgain() {
  if (typeof window !== 'undefined' && !window.confirm('Start over? All entered details will be cleared.')) return
  resetForm()
  navigateTo('/quote')
}
</script>

<template>
  <div class="max-w-3xl mx-auto px-6 pt-24 pb-32">

    <!-- Header -->
    <div class="mb-10">
      <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-accent);">Review your quote</p>
      <h1 class="text-[32px] lg:text-[40px] font-bold tracking-tight leading-tight mb-3" style="color: var(--color-text);">
        Looks good?
      </h1>
      <p class="text-[15px] leading-relaxed" style="color: var(--color-text-secondary);">
        Take a moment to review the details below. When you're happy, send it and our team
        will be in touch shortly.
      </p>
    </div>

    <!-- Config gates -->
    <div v-if="configLoading" class="text-center py-12" style="color: var(--color-text-secondary);">
      Loading pricing config…
    </div>
    <div v-else-if="configError" class="rounded-xl border px-5 py-4 mb-8" style="color: var(--color-danger); border-color: var(--color-danger);">
      {{ configError }}
    </div>

    <template v-else>
      <!-- ── Invoice card ───────────────────────────────────────────────── -->
      <div class="rounded-2xl border overflow-hidden"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">

        <!-- Header strip -->
        <div class="px-6 sm:px-8 py-6 border-b" :style="{ borderColor: 'var(--color-border)' }">
          <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
              <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-accent);">Quotation Preview</p>
              <h2 class="text-[24px] font-bold tracking-tight" style="color: var(--color-text);">Project quote</h2>
            </div>
            <div class="text-right">
              <p class="text-[16px] font-bold tracking-tight leading-none" style="color: var(--color-text);">AXEL NOVA</p>
              <p class="text-[10px] uppercase tracking-widest mt-1" style="color: var(--color-text-tertiary);">Ventures</p>
            </div>
          </div>
        </div>

        <!-- Bill to + dates -->
        <div class="grid sm:grid-cols-2 gap-6 px-6 sm:px-8 py-6 border-b" :style="{ borderColor: 'var(--color-border)' }">
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Prepared for</p>
            <p class="text-[14px] font-semibold" style="color: var(--color-text);">{{ form.name }}</p>
            <p v-if="form.company" class="text-[13px]" style="color: var(--color-text-secondary);">{{ form.company }}</p>
            <p class="text-[13px]" style="color: var(--color-text-secondary);">{{ form.email }}</p>
            <p class="text-[13px]" style="color: var(--color-text-secondary);">{{ form.phone }}</p>
          </div>
          <div class="sm:text-right">
            <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Quotation date</p>
            <p class="text-[14px] font-semibold" style="color: var(--color-text);">{{ todayDate }}</p>
            <p class="text-[11px] font-semibold uppercase tracking-widest mt-3 mb-1" style="color: var(--color-text-tertiary);">Valid until</p>
            <p class="text-[13px]" style="color: var(--color-text-secondary);">{{ validUntilDate }}</p>
          </div>
        </div>

        <!-- Project type -->
        <div class="px-6 sm:px-8 py-6 border-b" :style="{ borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Project</p>
          <div class="flex items-center gap-3">
            <UIcon v-if="currentCategory" :name="currentCategory.icon" class="size-5 shrink-0" style="color: var(--color-accent);" />
            <div>
              <p class="text-[15px] font-semibold" style="color: var(--color-text);">
                {{ currentCategory?.label }}
                <span v-if="currentPackage" style="color: var(--color-text-tertiary);"> — {{ currentPackage.name }}</span>
              </p>
              <p v-if="currentPackage" class="text-[12px]" style="color: var(--color-text-secondary);">{{ currentPackage.tagline }}</p>
            </div>
          </div>
        </div>

        <!-- Scope -->
        <div v-if="scopeSummary.length || form.notSureNotes" class="px-6 sm:px-8 py-6 border-b" :style="{ borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Scope</p>
          <div v-if="form.packageKey === 'not_sure'" class="text-[13px] leading-relaxed whitespace-pre-line" style="color: var(--color-text-secondary);">
            {{ form.notSureNotes }}
          </div>
          <div v-else class="space-y-2.5">
            <div v-for="item in scopeSummary" :key="item.label" class="flex justify-between gap-4">
              <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ item.label }}</span>
              <span class="text-[13px] font-medium text-right" style="color: var(--color-text);">{{ item.value }}</span>
            </div>
          </div>
        </div>

        <!-- Tech stack -->
        <div v-if="form.frontendTech || form.backendTech || form.hostingPref" class="px-6 sm:px-8 py-6 border-b" :style="{ borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Tech stack</p>
          <div class="space-y-2.5">
            <div v-if="form.frontendTech" class="flex justify-between">
              <span class="text-[13px]" style="color: var(--color-text-secondary);">Frontend</span>
              <span class="text-[13px] font-medium" style="color: var(--color-text);">{{ form.frontendTech }}</span>
            </div>
            <div v-if="form.backendTech" class="flex justify-between">
              <span class="text-[13px]" style="color: var(--color-text-secondary);">Backend</span>
              <span class="text-[13px] font-medium" style="color: var(--color-text);">{{ form.backendTech }}</span>
            </div>
            <div v-if="form.hostingPref" class="flex justify-between">
              <span class="text-[13px]" style="color: var(--color-text-secondary);">Hosting</span>
              <span class="text-[13px] font-medium" style="color: var(--color-text);">{{ form.hostingPref }}</span>
            </div>
          </div>
        </div>

        <!-- Add-ons -->
        <div v-if="form.addonKeys.length && config" class="px-6 sm:px-8 py-6 border-b" :style="{ borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Add-ons</p>
          <div class="space-y-2.5">
            <div v-for="key in form.addonKeys" :key="key" class="flex justify-between gap-4">
              <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ config.addons[key]?.label }}</span>
              <span class="text-[13px] font-medium" style="color: var(--color-text);">+{{ fmtMyr(config.addons[key]?.amount ?? 0) }}</span>
            </div>
          </div>
        </div>

        <!-- Timeline & budget -->
        <div v-if="form.budgetRange || form.rush" class="px-6 sm:px-8 py-6 border-b" :style="{ borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Timeline & budget</p>
          <div class="space-y-2.5">
            <div v-if="form.budgetRange" class="flex justify-between">
              <span class="text-[13px]" style="color: var(--color-text-secondary);">Budget range</span>
              <span class="text-[13px] font-medium" style="color: var(--color-text);">{{ form.budgetRange }}</span>
            </div>
            <div v-if="form.rush" class="flex justify-between">
              <span class="text-[13px]" style="color: var(--color-text-secondary);">Rush delivery</span>
              <span class="text-[13px] font-medium" style="color: var(--color-accent);">+20%, ~30% faster</span>
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div v-if="form.notes" class="px-6 sm:px-8 py-6 border-b" :style="{ borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Additional notes</p>
          <p class="text-[13px] leading-relaxed whitespace-pre-line" style="color: var(--color-text-secondary);">{{ form.notes }}</p>
        </div>

        <!-- Total / footer -->
        <div v-if="estimate" class="px-6 sm:px-8 py-6" :style="{ background: 'var(--color-accent-soft)' }">
          <div v-if="estimate.breakdown.length > 1" class="space-y-2 mb-5 pb-4 border-b" :style="{ borderColor: 'var(--color-border)' }">
            <p class="text-[11px] font-semibold uppercase tracking-widest mb-2" style="color: var(--color-text-tertiary);">Price breakdown</p>
            <div v-for="(line, i) in estimate.breakdown" :key="i" class="flex justify-between gap-4">
              <span class="text-[12px]" style="color: var(--color-text-secondary);">{{ line[0] }}</span>
              <span v-if="line[1] > 0" class="text-[12px] font-medium" style="color: var(--color-text);">+{{ fmtMyr(line[1]) }}</span>
            </div>
          </div>
          <div class="flex items-baseline justify-between gap-4 flex-wrap">
            <div>
              <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Estimated total</p>
              <p class="text-[12px]" style="color: var(--color-text-secondary);">
                Timeline: <span class="font-medium" style="color: var(--color-text);">{{ formatEta(estimate.etaValue, estimate.etaUnit) }}</span>
              </p>
            </div>
            <div class="text-right">
              <p class="text-[26px] font-bold tracking-tight leading-none" style="color: var(--color-text);">
                {{ fmtMyr(estimate.minMyr) }}
                <span class="text-[18px]" style="color: var(--color-text-tertiary);">–</span>
                {{ fmtMyr(estimate.maxMyr) }}
              </p>
              <p class="text-[11px] mt-1" style="color: var(--color-text-tertiary);">MYR, excl. SST</p>
            </div>
          </div>
        </div>

        <!-- "Not sure" packages don't have a numeric estimate yet -->
        <div v-else class="px-6 sm:px-8 py-6" :style="{ background: 'var(--color-accent-soft)' }">
          <p class="text-[13px] leading-relaxed" style="color: var(--color-text-secondary);">
            Pricing will be confirmed after a scoping call. Send the details below and the
            team will reach out with a tailored quote.
          </p>
        </div>
      </div>

      <p class="text-[11px] leading-relaxed mt-4 text-center" style="color: var(--color-text-tertiary);">
        Estimates are in MYR, excl. SST. Final price is confirmed after a scoping call.
      </p>

      <p v-if="error" class="mt-6 text-[12px] text-center" style="color: var(--color-danger);">{{ error }}</p>

      <!-- ── Actions ─────────────────────────────────────────────────────── -->
      <div class="mt-8 grid sm:grid-cols-3 gap-3">
        <button type="button" class="btn-pill btn-pill-ghost justify-center"
          :disabled="loading"
          @click="backToEdit">
          <UIcon name="i-lucide-arrow-left" class="size-4" />
          Edit details
        </button>
        <button type="button" class="btn-pill btn-pill-ghost justify-center"
          :disabled="loading"
          @click="startAgain">
          <UIcon name="i-lucide-rotate-ccw" class="size-4" />
          Start again
        </button>
        <button type="button" class="btn-pill btn-pill-accent justify-center"
          :disabled="loading || !canSubmit"
          :style="{ opacity: loading || !canSubmit ? '0.6' : '1', cursor: loading || !canSubmit ? 'not-allowed' : 'pointer' }"
          @click="handleSubmit">
          {{ loading ? 'Sending…' : 'Send to admin →' }}
        </button>
      </div>
    </template>
  </div>
</template>
