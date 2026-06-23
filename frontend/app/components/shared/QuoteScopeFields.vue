<script setup lang="ts">
import type { EstimateResult } from '~/composables/usePricingEngine'
import type { QuoteScopeState } from '~/composables/quoteScope'
import { deriveModifiers } from '~/composables/quoteScope'

// `state` is a reactive object owned by the parent; this component mutates its
// fields in place (same ergonomics as the legacy public quote form) and emits
// the derived modifiers + live estimate so the parent can re-price / show a sidebar.
const props = defineProps<{ state: QuoteScopeState }>()
const emit = defineEmits<{
  'update:estimate': [value: EstimateResult | null]
  'update:modifiers': [value: Record<string, boolean | number>]
}>()

const { config, configLoading, configError, loadConfig, calculate, fmtMyr } = usePricingEngine()

onMounted(loadConfig)

const categories = computed(() => config.value?.categories ?? [])
const currentCategory = computed(() => categories.value.find(c => c.key === props.state.categoryKey))

const modifiers = computed(() => deriveModifiers(props.state))

const estimate = computed<EstimateResult | null>(() => {
  if (!props.state.packageKey || !config.value) return null
  return calculate(props.state.packageKey, modifiers.value, props.state.addonKeys, props.state.rush)
})

watch(estimate, v => emit('update:estimate', v), { immediate: true })
watch(modifiers, v => emit('update:modifiers', v), { immediate: true, deep: true })

function pickCategory(key: string) {
  props.state.categoryKey = key
  props.state.packageKey = ''
}

function toggleInArray(arr: string[], value: string) {
  const i = arr.indexOf(value)
  if (i === -1) arr.push(value)
  else arr.splice(i, 1)
}
</script>

<template>
  <div class="space-y-10">

    <div v-if="configLoading && !config" class="text-[13px]" style="color: var(--color-text-secondary);">Loading pricing…</div>
    <div v-else-if="configError" class="rounded-xl border px-4 py-3 text-[13px]" style="color: var(--color-danger); border-color: var(--color-danger);">
      {{ configError }}
    </div>

    <template v-else>
      <!-- Category + package -->
      <section>
        <p class="quote-label mb-3">Category</p>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
          <button v-for="cat in categories" :key="cat.key" type="button"
            class="flex items-center gap-3 rounded-xl border px-4 py-3.5 text-left transition-all"
            :style="{
              borderColor: state.categoryKey === cat.key ? 'var(--color-accent)' : 'var(--color-border)',
              background: state.categoryKey === cat.key ? 'var(--color-accent-soft)' : 'var(--color-bg-elevated)',
            }"
            @click="pickCategory(cat.key)">
            <UIcon :name="cat.icon" class="size-4 shrink-0"
              :style="{ color: state.categoryKey === cat.key ? 'var(--color-accent)' : 'var(--color-text-tertiary)' }" />
            <span class="text-[13px] font-medium"
              :style="{ color: state.categoryKey === cat.key ? 'var(--color-accent)' : 'var(--color-text)' }">
              {{ cat.label }}
            </span>
          </button>
        </div>

        <Transition name="tab" mode="out-in">
          <div v-if="currentCategory" :key="state.categoryKey" class="grid sm:grid-cols-3 gap-3">
            <button v-for="pkg in currentCategory.packages" :key="pkg.key" type="button"
              class="rounded-xl border p-4 text-left transition-all"
              :style="{
                borderColor: state.packageKey === pkg.key ? 'var(--color-accent)' : 'var(--color-border)',
                background: state.packageKey === pkg.key ? 'var(--color-accent-soft)' : 'var(--color-bg-elevated)',
              }"
              @click="state.packageKey = pkg.key">
              <p class="text-[13px] font-semibold mb-0.5"
                :style="{ color: state.packageKey === pkg.key ? 'var(--color-accent)' : 'var(--color-text)' }">
                {{ pkg.name }}
              </p>
              <p class="text-[11px]" style="color: var(--color-text-tertiary);">{{ pkg.tagline }}</p>
              <p v-if="config && config.base_packages[pkg.key]" class="text-[11px] mt-2 font-medium" style="color: var(--color-text-secondary);">
                from {{ fmtMyr(config.base_packages[pkg.key]?.min ?? 0) }}
              </p>
            </button>
          </div>
        </Transition>
      </section>

      <!-- Scope details (per category) -->
      <Transition name="tab" mode="out-in">
        <section v-if="state.categoryKey && state.packageKey" :key="`scope-${state.categoryKey}`">
          <p class="quote-label mb-4">Scope details</p>

          <!-- Web -->
          <div v-if="state.categoryKey === 'web'" class="space-y-6">
            <div class="space-y-2">
              <label class="quote-label">Number of pages: <strong style="color:var(--color-text)">{{ state.pages }}</strong></label>
              <input v-model.number="state.pages" type="range" min="1" max="20" class="quote-range w-full" />
            </div>
            <div class="flex flex-wrap gap-4">
              <label class="quote-toggle">
                <input v-model="state.cms" type="checkbox" class="sr-only" />
                <span class="quote-toggle-track" :class="{ active: state.cms }"></span>
                <span class="text-[13px]" style="color: var(--color-text);">CMS / editable content</span>
              </label>
              <label class="quote-toggle">
                <input v-model="state.bookingFlow" type="checkbox" class="sr-only" />
                <span class="quote-toggle-track" :class="{ active: state.bookingFlow }"></span>
                <span class="text-[13px]" style="color: var(--color-text);">Booking / enquiry flow</span>
              </label>
            </div>
            <div class="space-y-1.5">
              <label class="quote-label">Languages needed</label>
              <div class="flex flex-wrap gap-2">
                <button v-for="lang in ['English', 'Bahasa Malaysia', 'Mandarin', 'Arabic']" :key="lang" type="button"
                  class="text-[12px] px-3.5 py-1.5 rounded-full border transition-all"
                  :style="{
                    borderColor: state.languages.includes(lang) ? 'var(--color-accent)' : 'var(--color-border)',
                    background: state.languages.includes(lang) ? 'var(--color-accent-soft)' : 'transparent',
                    color: state.languages.includes(lang) ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                  }"
                  @click="toggleInArray(state.languages, lang)">
                  {{ lang }}
                </button>
              </div>
            </div>
          </div>

          <!-- Dashboard -->
          <div v-else-if="state.categoryKey === 'dashboard'" class="space-y-6">
            <div class="space-y-2">
              <label class="quote-label">Number of modules: <strong style="color:var(--color-text)">{{ state.modules }}</strong></label>
              <input v-model.number="state.modules" type="range" min="1" max="15" class="quote-range w-full" />
            </div>
            <div class="space-y-2">
              <label class="quote-label">User roles: <strong style="color:var(--color-text)">{{ state.userRoles }}</strong></label>
              <input v-model.number="state.userRoles" type="range" min="1" max="6" class="quote-range w-full" />
            </div>
            <label class="quote-toggle">
              <input v-model="state.realTime" type="checkbox" class="sr-only" />
              <span class="quote-toggle-track" :class="{ active: state.realTime }"></span>
              <span class="text-[13px]" style="color: var(--color-text);">Real-time updates (WebSocket)</span>
            </label>
            <div class="space-y-1.5">
              <label class="quote-label">Charts complexity</label>
              <div class="flex flex-wrap gap-2">
                <button v-for="opt in [{ key: 'none', label: 'None' }, { key: 'basic', label: 'Basic charts' }, { key: 'advanced', label: 'Advanced / custom' }]" :key="opt.key"
                  type="button"
                  class="text-[12px] px-3.5 py-1.5 rounded-full border transition-all"
                  :style="{
                    borderColor: state.chartsComplexity === opt.key ? 'var(--color-accent)' : 'var(--color-border)',
                    background: state.chartsComplexity === opt.key ? 'var(--color-accent-soft)' : 'transparent',
                    color: state.chartsComplexity === opt.key ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                  }"
                  @click="state.chartsComplexity = opt.key as 'none'|'basic'|'advanced'">
                  {{ opt.label }}
                </button>
              </div>
            </div>
          </div>

          <!-- Design & Frontend (combined `design-frontend` category — fixes the legacy data-drift) -->
          <div v-else-if="state.categoryKey === 'design-frontend'" class="space-y-6">
            <div class="space-y-2">
              <label class="quote-label">Number of screens: <strong style="color:var(--color-text)">{{ state.screensCount }}</strong></label>
              <input v-model.number="state.screensCount" type="range" min="1" max="40" class="quote-range w-full" />
            </div>
            <div class="flex flex-wrap gap-4">
              <label class="quote-toggle">
                <input v-model="state.designSystem" type="checkbox" class="sr-only" />
                <span class="quote-toggle-track" :class="{ active: state.designSystem }"></span>
                <span class="text-[13px]" style="color: var(--color-text);">Full design system (tokens + components)</span>
              </label>
              <label class="quote-toggle">
                <input v-model="state.prototype" type="checkbox" class="sr-only" />
                <span class="quote-toggle-track" :class="{ active: state.prototype }"></span>
                <span class="text-[13px]" style="color: var(--color-text);">Interactive prototype</span>
              </label>
            </div>
            <div class="grid sm:grid-cols-2 gap-5">
              <div class="space-y-2">
                <label class="quote-label">Front-end components: <strong style="color:var(--color-text)">{{ state.componentsCount }}</strong></label>
                <input v-model.number="state.componentsCount" type="range" min="1" max="50" class="quote-range w-full" />
              </div>
              <div class="space-y-2">
                <label class="quote-label">Pages built: <strong style="color:var(--color-text)">{{ state.pagesCount }}</strong></label>
                <input v-model.number="state.pagesCount" type="range" min="1" max="30" class="quote-range w-full" />
              </div>
            </div>
            <div class="flex flex-wrap gap-4">
              <label class="quote-toggle">
                <input v-model="state.stateManagement" type="checkbox" class="sr-only" />
                <span class="quote-toggle-track" :class="{ active: state.stateManagement }"></span>
                <span class="text-[13px]" style="color: var(--color-text);">State management (Pinia)</span>
              </label>
              <label class="quote-toggle">
                <input v-model="state.testing" type="checkbox" class="sr-only" />
                <span class="quote-toggle-track" :class="{ active: state.testing }"></span>
                <span class="text-[13px]" style="color: var(--color-text);">Unit tests included</span>
              </label>
            </div>
          </div>

          <!-- SaaS -->
          <div v-else-if="state.categoryKey === 'saas'" class="space-y-6">
            <div class="space-y-1.5">
              <label class="quote-label">Core features (one per line)</label>
              <textarea v-model="state.coreFeatures" rows="4" placeholder="e.g. User auth, Team management, Analytics dashboard…" class="contact-input resize-none w-full"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" />
            </div>
            <div class="space-y-1.5">
              <label class="quote-label">Auth methods</label>
              <div class="flex flex-wrap gap-2">
                <button v-for="met in ['Email/password', 'Magic link', 'Google SSO', 'Phone OTP']" :key="met" type="button"
                  class="text-[12px] px-3.5 py-1.5 rounded-full border transition-all"
                  :style="{
                    borderColor: state.authMethods.includes(met) ? 'var(--color-accent)' : 'var(--color-border)',
                    background: state.authMethods.includes(met) ? 'var(--color-accent-soft)' : 'transparent',
                    color: state.authMethods.includes(met) ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                  }"
                  @click="toggleInArray(state.authMethods, met)">
                  {{ met }}
                </button>
              </div>
            </div>
            <div class="space-y-1.5">
              <label class="quote-label">Payment integration</label>
              <div class="flex flex-wrap gap-2">
                <button v-for="p in ['None', 'Stripe', 'FPX / iPay88', 'Both']" :key="p" type="button"
                  class="text-[12px] px-3.5 py-1.5 rounded-full border transition-all"
                  :style="{
                    borderColor: state.paymentMethod === p ? 'var(--color-accent)' : 'var(--color-border)',
                    background: state.paymentMethod === p ? 'var(--color-accent-soft)' : 'transparent',
                    color: state.paymentMethod === p ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                  }"
                  @click="state.paymentMethod = p">
                  {{ p }}
                </button>
              </div>
            </div>
            <label class="quote-toggle">
              <input v-model="state.adminPortal" type="checkbox" class="sr-only" />
              <span class="quote-toggle-track" :class="{ active: state.adminPortal }"></span>
              <span class="text-[13px]" style="color: var(--color-text);">Admin portal needed</span>
            </label>
          </div>
        </section>
      </Transition>

      <!-- Add-ons -->
      <section v-if="state.packageKey && config">
        <p class="quote-label mb-4">Add-ons</p>
        <div class="grid sm:grid-cols-2 gap-3">
          <button v-for="[key, addon] in Object.entries(config.addons)" :key="key" type="button"
            class="flex items-center justify-between rounded-xl border px-4 py-3.5 text-left transition-all"
            :style="{
              borderColor: state.addonKeys.includes(key) ? 'var(--color-accent)' : 'var(--color-border)',
              background: state.addonKeys.includes(key) ? 'var(--color-accent-soft)' : 'var(--color-bg-elevated)',
            }"
            @click="toggleInArray(state.addonKeys, key)">
            <p class="text-[13px] font-medium"
              :style="{ color: state.addonKeys.includes(key) ? 'var(--color-accent)' : 'var(--color-text)' }">
              {{ addon.label }}
            </p>
            <p class="text-[12px] font-semibold shrink-0 ml-3"
              :style="{ color: state.addonKeys.includes(key) ? 'var(--color-accent)' : 'var(--color-text-secondary)' }">
              +{{ fmtMyr(addon.amount) }}
            </p>
          </button>
        </div>
      </section>

      <!-- Rush -->
      <section v-if="state.packageKey">
        <label class="quote-toggle flex items-center gap-3">
          <input v-model="state.rush" type="checkbox" class="sr-only" />
          <span class="quote-toggle-track" :class="{ active: state.rush }"></span>
          <span>
            <span class="text-[13px] font-medium" style="color: var(--color-text);">Rush delivery</span>
            <span class="text-[12px] ml-2" style="color: var(--color-text-tertiary);">(+20%, timeline reduced ~30%)</span>
          </span>
        </label>
      </section>
    </template>
  </div>
</template>

<style scoped>
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
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
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
  .tab-leave-active {
    transition: none;
  }
}
</style>
