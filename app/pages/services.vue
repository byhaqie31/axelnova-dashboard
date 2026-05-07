<script setup lang="ts">
import { serviceCategories } from '~/data/services'
import SectionHeader from '~/components/shared/SectionHeader.vue'

// ── Currency ──────────────────────────────────────────────────────────────────
type CurrencyCode = 'MYR' | 'USD' | 'GBP' | 'SGD'

const currencyMeta: Record<CurrencyCode, { symbol: string; rate: number }> = {
  MYR: { symbol: 'RM',  rate: 1      },
  USD: { symbol: '$',   rate: 0.225  },
  GBP: { symbol: '£',   rate: 0.178  },
  SGD: { symbol: 'S$',  rate: 0.303  },
}
const currencyCodes: CurrencyCode[] = ['MYR', 'USD', 'GBP', 'SGD']
const activeCurrency = ref<CurrencyCode>('MYR')

function fmtAmt(myr: number): string {
  const { symbol, rate } = currencyMeta[activeCurrency.value]
  const isMYR = activeCurrency.value === 'MYR'
  const v = isMYR
    ? Math.round(myr / 100) * 100
    : Math.round(myr * rate / 50) * 50

  if (v >= 1000) {
    const k = v / 1000
    const s = k % 1 === 0 ? k.toFixed(0) : k.toFixed(1)
    return isMYR ? `${symbol} ${s}k` : `${symbol}${s}k`
  }
  return isMYR ? `${symbol} ${v.toLocaleString()}` : `${symbol}${v.toLocaleString()}`
}

function fmtPrice(min: number, max: number | null): string {
  if (!max) return `${fmtAmt(min)}+`
  return `${fmtAmt(min)} – ${fmtAmt(max)}`
}

// ── Service tabs ──────────────────────────────────────────────────────────────
const activeCat = ref('web')
const currentCategory = computed(
  () => serviceCategories.find(c => c.id === activeCat.value)!
)

// ── Estimator ─────────────────────────────────────────────────────────────────
type ProjectType = 'landing' | 'dashboard' | 'ecommerce' | 'custom'
type Urgency = 'relaxed' | 'standard' | 'rush'

const projectType = ref<ProjectType>('dashboard')
const moduleCount = ref(3)
const apiIntegration = ref(true)
const urgency = ref<Urgency>('standard')

const baseByType: Record<ProjectType, number> = {
  landing: 1500,
  dashboard: 4000,
  ecommerce: 6500,
  custom: 8000,
}
const weeksByType: Record<ProjectType, number> = {
  landing: 1,
  dashboard: 3,
  ecommerce: 5,
  custom: 6,
}

const estimate = computed(() => {
  let price = baseByType[projectType.value]
  price += (moduleCount.value - 1) * 700
  if (apiIntegration.value) price += 800
  let weeks = weeksByType[projectType.value] + Math.max(0, Math.floor((moduleCount.value - 1) / 3))

  if (urgency.value === 'rush') {
    price = Math.round(price * 1.2)
    weeks = Math.max(1, Math.round(weeks * 0.7))
  } else if (urgency.value === 'relaxed') {
    weeks = weeks + 1
  }

  return { price: Math.round(price / 100) * 100, weeks }
})

const projectTypeOptions = [
  { value: 'landing',   label: 'Landing page' },
  { value: 'dashboard', label: 'Dashboard' },
  { value: 'ecommerce', label: 'E-commerce' },
  { value: 'custom',    label: 'Custom build' },
]
const urgencyOptions = [
  { value: 'relaxed',  label: 'Relaxed' },
  { value: 'standard', label: 'Standard' },
  { value: 'rush',     label: 'Rush (+20%)' },
]

// ── Process + contact ─────────────────────────────────────────────────────────
const processSteps = [
  { n: 1, title: 'Discovery', desc: 'Scope, goals, success metrics.' },
  { n: 2, title: 'Design',    desc: 'Figma flows + component system.' },
  { n: 3, title: 'Build',     desc: 'Vue/Nuxt build, API integration, QA.' },
  { n: 4, title: 'Handover',  desc: 'Walkthrough, docs, support.' },
]

const contactChannels = [
  {
    id: 'whatsapp',
    label: 'WhatsApp',
    value: '+60 17-710 9486',
    helper: 'Fastest for quick questions and project chats.',
    href: 'https://wa.me/60177109486?text=Hi%20Qie%2C%20I%27d%20like%20to%20discuss%20a%20project.',
    target: '_blank',
    icon: 'i-fluent-chat-24-regular',
    iconColor: 'var(--color-success)',
    iconBg: 'rgba(48,209,88,0.14)',
    glow: 'radial-gradient(60% 80% at 0% 0%, rgba(48,209,88,0.12) 0%, transparent 55%)',
  },
  {
    id: 'email',
    label: 'Email',
    value: 'baihaqie@axelnova.tech',
    helper: 'Best for briefs, scope docs, and longer conversations.',
    href: 'mailto:baihaqie@axelnova.tech?subject=Project%20enquiry%20—%20axelnova.tech',
    target: '_self',
    icon: 'i-fluent-mail-24-regular',
    iconColor: 'var(--color-accent)',
    iconBg: 'var(--color-accent-soft)',
    glow: 'radial-gradient(60% 80% at 0% 0%, rgba(0,113,227,0.12) 0%, transparent 55%)',
  },
  {
    id: 'call',
    label: 'Call',
    value: '+60 17-710 9486',
    helper: 'For urgent or complex scope — book a quick voice call.',
    href: 'tel:+60177109486',
    target: '_self',
    icon: 'i-fluent-call-24-regular',
    iconColor: 'var(--grad-aurora-violet)',
    iconBg: 'rgba(168,85,247,0.14)',
    glow: 'radial-gradient(60% 80% at 0% 0%, rgba(168,85,247,0.12) 0%, transparent 55%)',
  },
]

useScrollReveal('.reveal')
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-24 pb-32">

    <!-- ── Pricing ───────────────────────────────────────────────────────────── -->
    <SectionHeader
      eyebrow="Services"
      title="Choose your engagement."
      subtitle="Five service tracks — from a landing page to a full product build. Priced transparently, delivered precisely."
    />

    <!-- Tab + currency row -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">

      <!-- Category tabs -->
      <div class="flex items-center gap-2 flex-wrap">
        <button
          v-for="cat in serviceCategories"
          :key="cat.id"
          class="inline-flex items-center gap-1.5 text-[13px] px-4 py-1.5 rounded-full border transition-all duration-200"
          :style="{
            borderColor: activeCat === cat.id ? 'transparent' : 'var(--color-border-strong)',
            background: activeCat === cat.id ? 'var(--color-text)' : 'transparent',
            color: activeCat === cat.id ? 'var(--color-bg)' : 'var(--color-text-secondary)',
            fontWeight: activeCat === cat.id ? '500' : '400',
            boxShadow: activeCat === cat.id ? 'var(--shadow-sm)' : 'none',
          }"
          @click="activeCat = cat.id"
        >
          <UIcon :name="cat.icon" class="size-3.5" />
          {{ cat.label }}
        </button>
      </div>

      <!-- Currency toggle -->
      <div class="flex items-center gap-1.5 shrink-0">
        <span class="text-[11px] font-medium uppercase tracking-wide mr-1" style="color: var(--color-text-tertiary);">Currency</span>
        <button
          v-for="code in currencyCodes"
          :key="code"
          class="text-[12px] px-3 py-1 rounded-full border transition-all duration-200"
          :style="{
            borderColor: activeCurrency === code ? 'var(--color-accent)' : 'var(--color-border)',
            background: activeCurrency === code ? 'var(--color-accent-soft)' : 'transparent',
            color: activeCurrency === code ? 'var(--color-accent)' : 'var(--color-text-tertiary)',
            fontWeight: activeCurrency === code ? '500' : '400',
          }"
          @click="activeCurrency = code"
        >
          {{ code }}
        </button>
      </div>
    </div>

    <!-- Animated tab content -->
    <Transition name="tab" mode="out-in">
      <div :key="activeCat" class="mb-32">

        <!-- Category description -->
        <p class="text-[15px] leading-relaxed mb-8 max-w-2xl" style="color: var(--color-text-secondary);">
          {{ currentCategory.description }}
        </p>

        <!-- Package cards -->
        <div class="grid gap-5 md:grid-cols-3">
          <div
            v-for="pkg in currentCategory.packages"
            :key="pkg.id"
            class="relative rounded-2xl border p-8 flex flex-col"
            :style="{
              background: 'var(--color-bg-secondary)',
              borderColor: pkg.featured ? 'var(--color-accent)' : 'var(--color-border)',
              borderWidth: pkg.featured ? '2px' : '1px',
            }"
          >
            <!-- Popular badge -->
            <span
              v-if="pkg.featured"
              class="absolute -top-3 left-1/2 -translate-x-1/2 text-[11px] font-medium px-3 py-1 rounded-full"
              style="background: var(--color-accent); color: white;"
            >
              Most popular
            </span>

            <!-- Name + tagline -->
            <h3 class="text-[20px] font-semibold tracking-tight mb-1" style="color: var(--color-text);">
              {{ pkg.name }}
            </h3>
            <p class="text-[13px] mb-6" style="color: var(--color-text-secondary);">
              {{ pkg.tagline }}
            </p>

            <!-- Price -->
            <div class="mb-1 tabular-nums">
              <span class="text-[34px] font-semibold tracking-tight">
                {{ fmtPrice(pkg.priceMin, pkg.priceMax) }}
              </span>
            </div>
            <p class="text-[12px] mb-6" style="color: var(--color-text-secondary);">
              {{ pkg.unit }}
            </p>

            <!-- Meta row -->
            <div
              class="flex items-center gap-4 pb-6 mb-6 border-b"
              :style="{ borderColor: 'var(--color-border)' }"
            >
              <span class="inline-flex items-center gap-1.5 text-[12px]" style="color: var(--color-text-secondary);">
                <UIcon name="i-lucide-clock" class="size-3.5 shrink-0" />
                {{ pkg.duration }}
              </span>
              <span
                v-if="pkg.revisions !== '—'"
                class="inline-flex items-center gap-1.5 text-[12px]"
                style="color: var(--color-text-secondary);"
              >
                <UIcon name="i-lucide-rotate-ccw" class="size-3.5 shrink-0" />
                {{ pkg.revisions }}
              </span>
            </div>

            <!-- Features -->
            <ul class="space-y-2.5 mb-8 flex-1">
              <li
                v-for="f in pkg.features"
                :key="f"
                class="flex items-start gap-2.5 text-[14px]"
                style="color: var(--color-text);"
              >
                <UIcon
                  name="i-fluent-checkmark-24-regular"
                  class="size-4 mt-0.5 shrink-0"
                  style="color: var(--color-accent);"
                />
                <span>{{ f }}</span>
              </li>
            </ul>

            <!-- CTA -->
            <a
              href="mailto:baihaqie@axelnova.tech"
              class="btn-pill text-center"
              :class="pkg.featured ? 'btn-pill-accent' : 'btn-pill-primary'"
            >
              {{ pkg.cta }}
            </a>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ── Estimator ─────────────────────────────────────────────────────────── -->
    <section class="reveal mb-32">
      <SectionHeader
        eyebrow="Estimator"
        title="Estimate your project."
        subtitle="Adjust the inputs — see ballpark cost and timeline update live."
      />

      <div class="grid lg:grid-cols-2 gap-6">
        <div
          class="rounded-2xl border p-8 space-y-7"
          :style="{ background: 'var(--color-bg-secondary)', borderColor: 'var(--color-border)' }"
        >
          <div>
            <label class="text-[12px] font-medium block mb-3" style="color: var(--color-text-secondary);">Project type</label>
            <div class="grid grid-cols-2 gap-2">
              <button
                v-for="o in projectTypeOptions"
                :key="o.value"
                class="text-[13px] py-2.5 rounded-lg border transition-colors"
                :style="{
                  borderColor: 'var(--color-border)',
                  background: projectType === o.value ? 'var(--color-text)' : 'transparent',
                  color: projectType === o.value ? 'var(--color-bg)' : 'var(--color-text)',
                  fontWeight: projectType === o.value ? '500' : '400',
                }"
                @click="projectType = o.value as ProjectType"
              >
                {{ o.label }}
              </button>
            </div>
          </div>

          <div>
            <label class="text-[12px] font-medium flex items-center justify-between mb-3" style="color: var(--color-text-secondary);">
              <span>Pages / modules</span>
              <span class="text-[15px] font-semibold tabular-nums" style="color: var(--color-text);">{{ moduleCount }}</span>
            </label>
            <input
              v-model.number="moduleCount"
              type="range" min="1" max="10" step="1"
              class="w-full accent-cyan-500"
            />
          </div>

          <div class="flex items-center justify-between">
            <label class="text-[13px] font-medium" style="color: var(--color-text);">API integrations</label>
            <button
              role="switch"
              :aria-checked="apiIntegration"
              class="relative w-11 h-6 rounded-full border transition-colors"
              :style="{
                borderColor: 'var(--color-border)',
                background: apiIntegration ? 'var(--color-accent)' : 'var(--color-bg)',
              }"
              @click="apiIntegration = !apiIntegration"
            >
              <span
                class="absolute top-0.5 size-5 rounded-full bg-white shadow transition-all"
                :style="{ left: apiIntegration ? '22px' : '2px' }"
              />
            </button>
          </div>

          <div>
            <label class="text-[12px] font-medium block mb-3" style="color: var(--color-text-secondary);">Timeline</label>
            <div class="grid grid-cols-3 gap-2">
              <button
                v-for="o in urgencyOptions"
                :key="o.value"
                class="text-[13px] py-2.5 rounded-lg border transition-colors"
                :style="{
                  borderColor: 'var(--color-border)',
                  background: urgency === o.value ? 'var(--color-text)' : 'transparent',
                  color: urgency === o.value ? 'var(--color-bg)' : 'var(--color-text)',
                  fontWeight: urgency === o.value ? '500' : '400',
                }"
                @click="urgency = o.value as Urgency"
              >
                {{ o.label }}
              </button>
            </div>
          </div>
        </div>

        <div class="grid grid-rows-2 gap-5">
          <div
            class="rounded-2xl border p-8 flex flex-col justify-center"
            :style="{ background: 'var(--color-bg-secondary)', borderColor: 'var(--color-border)' }"
          >
            <p class="text-[12px] font-medium mb-2" style="color: var(--color-text-secondary);">Estimated cost</p>
            <p class="text-5xl font-semibold tracking-tight tabular-nums">{{ fmtAmt(estimate.price) }}</p>
          </div>
          <div
            class="rounded-2xl border p-8 flex flex-col justify-center"
            :style="{ background: 'var(--color-bg-secondary)', borderColor: 'var(--color-border)' }"
          >
            <p class="text-[12px] font-medium mb-2" style="color: var(--color-text-secondary);">Estimated timeline</p>
            <p class="text-5xl font-semibold tracking-tight tabular-nums">{{ estimate.weeks }} {{ estimate.weeks === 1 ? 'week' : 'weeks' }}</p>
          </div>
        </div>
      </div>

      <p class="text-[12px] mt-4" style="color: var(--color-text-secondary);">
        Rough estimate. Final scope is agreed in writing before any work begins.
      </p>
    </section>

    <!-- ── Process ───────────────────────────────────────────────────────────── -->
    <section class="reveal mb-32">
      <SectionHeader
        eyebrow="Process"
        title="How we work together."
      />

      <div class="grid grid-cols-1 md:grid-cols-4 gap-8 relative">
        <div
          v-for="(step, i) in processSteps"
          :key="step.n"
          class="relative"
        >
          <div
            v-if="i < processSteps.length - 1"
            class="hidden md:block absolute top-5 left-12 right-0 h-px"
            style="background: var(--color-border);"
          />
          <div
            class="size-10 rounded-full border flex items-center justify-center text-[14px] font-medium mb-5 relative z-10"
            :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-secondary)' }"
          >
            {{ step.n }}
          </div>
          <h4 class="text-xl font-semibold tracking-tight mb-2">{{ step.title }}</h4>
          <p class="text-[14px] leading-relaxed" style="color: var(--color-text-secondary);">{{ step.desc }}</p>
        </div>
      </div>
    </section>

    <!-- ── Contact ───────────────────────────────────────────────────────────── -->
    <section class="reveal">
      <SectionHeader
        eyebrow="Contact"
        title="Let's talk."
        subtitle="Pick the channel that suits you. I usually reply within a working day."
      />

      <div class="grid gap-5 md:grid-cols-3">
        <a
          v-for="c in contactChannels"
          :key="c.id"
          :href="c.href"
          :target="c.target"
          :rel="c.target === '_blank' ? 'noopener' : undefined"
          class="contact-card group relative block rounded-2xl border p-7 transition-all duration-300 overflow-hidden"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
        >
          <span
            aria-hidden
            class="contact-glow pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-500"
            :style="{ background: c.glow }"
          />

          <div class="relative flex items-start justify-between mb-6">
            <div
              class="size-11 rounded-xl flex items-center justify-center"
              :style="{ background: c.iconBg, border: '1px solid var(--color-border)' }"
            >
              <UIcon :name="c.icon" class="size-5" :style="{ color: c.iconColor }" />
            </div>
            <UIcon
              name="i-fluent-arrow-up-right-24-regular"
              class="size-4 opacity-0 group-hover:opacity-100 transition-all duration-300"
              style="color: var(--color-text-secondary);"
            />
          </div>

          <p class="relative text-[12px] font-medium mb-1.5" style="color: var(--color-text-secondary);">{{ c.label }}</p>
          <p class="relative text-[18px] font-semibold tracking-tight mb-2" style="color: var(--color-text);">{{ c.value }}</p>
          <p class="relative text-[13px] leading-relaxed" style="color: var(--color-text-secondary);">{{ c.helper }}</p>
        </a>
      </div>
    </section>
  </div>
</template>

<style scoped>
.contact-card:hover {
  transform: translateY(-4px);
  border-color: var(--color-border-strong) !important;
  box-shadow: var(--shadow-card-hover);
}
.contact-card:hover .contact-glow {
  opacity: 1;
}

/* Tab switch animation */
.tab-enter-active,
.tab-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease;
}
.tab-enter-from,
.tab-leave-to {
  opacity: 0;
  transform: translateY(6px);
}
</style>
