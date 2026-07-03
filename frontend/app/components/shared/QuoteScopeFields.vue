<script setup lang="ts">
import type { EstimateResult, ScopeField } from '~/composables/usePricingEngine'
import type { QuoteScopeState, ScopeValue } from '~/composables/quoteScope'
import { seedScopeDefaults } from '~/composables/quoteScope'

// `state` is a reactive object owned by the parent; this component mutates its
// fields in place and emits the live scope values + estimate so the parent can
// re-price / show a sidebar. Scope inputs are data-driven (admin-managed
// service_scope_fields) — rendered generically by type.
const props = defineProps<{
  state: QuoteScopeState
  /** Show the red required-asterisk on the package label (standard quotes). */
  requirePackage?: boolean
  /** Validation message to surface under the package picker, set by the parent. */
  packageError?: string
}>()
const emit = defineEmits<{
  'update:estimate': [value: EstimateResult | null]
  'update:scopeValues': [value: Record<string, ScopeValue>]
}>()

const { config, configLoading, configError, loadConfig, calculate, fmtMyrExact } = usePricingEngine()

onMounted(loadConfig)

const categories = computed(() => config.value?.categories ?? [])
const currentCategory = computed(() => categories.value.find(c => c.key === props.state.categoryKey))

// All scope fields for the selected category, and the subset visible for the
// selected package (applies_to gate). Split by type for the layout.
const categoryFields = computed<ScopeField[]>(() => config.value?.scope_fields?.[props.state.categoryKey] ?? [])
const visibleFields = computed<ScopeField[]>(() => categoryFields.value.filter(f =>
  f.applies_to === 'all' || (Array.isArray(f.applies_to) && f.applies_to.includes(props.state.packageKey)),
))
const sliderFields = computed(() => visibleFields.value.filter(f => f.type === 'slider'))
const toggleFields = computed(() => visibleFields.value.filter(f => f.type === 'toggle'))
const selectFields = computed(() => visibleFields.value.filter(f => f.type === 'select'))

// Fill defaults when the category's field set becomes known / changes.
watch(categoryFields, (fields) => {
  if (fields.length) props.state.scopeValues = seedScopeDefaults(fields, props.state.scopeValues)
}, { immediate: true })

const estimate = computed<EstimateResult | null>(() => {
  if (!props.state.packageKey || !config.value) return null
  return calculate(props.state.packageKey, props.state.scopeValues, props.state.addonKeys, props.state.rush)
})

watch(estimate, v => emit('update:estimate', v), { immediate: true })
watch(() => props.state.scopeValues, v => emit('update:scopeValues', v), { immediate: true, deep: true })

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
          <button
v-for="cat in categories" :key="cat.key" type="button"
            class="flex items-center gap-3 rounded-xl border px-4 py-3.5 text-left transition-all"
            :style="{
              borderColor: state.categoryKey === cat.key ? 'var(--color-accent)' : 'var(--color-border)',
              background: state.categoryKey === cat.key ? 'var(--color-accent-soft)' : 'var(--color-bg-elevated)',
            }"
            @click="pickCategory(cat.key)">
            <UIcon
:name="cat.icon" class="size-4 shrink-0"
              :style="{ color: state.categoryKey === cat.key ? 'var(--color-accent)' : 'var(--color-text-tertiary)' }" />
            <span
class="text-[13px] font-medium"
              :style="{ color: state.categoryKey === cat.key ? 'var(--color-accent)' : 'var(--color-text)' }">
              {{ cat.label }}
            </span>
          </button>
        </div>

        <p class="quote-label mb-3">Package <span v-if="requirePackage" style="color: var(--color-danger);">*</span></p>
        <Transition name="tab" mode="out-in">
          <div v-if="currentCategory" :key="state.categoryKey" class="grid sm:grid-cols-3 gap-3">
            <button
v-for="pkg in currentCategory.packages" :key="pkg.key" type="button"
              class="rounded-xl border p-4 text-left transition-all"
              :style="{
                borderColor: state.packageKey === pkg.key ? 'var(--color-accent)' : 'var(--color-border)',
                background: state.packageKey === pkg.key ? 'var(--color-accent-soft)' : 'var(--color-bg-elevated)',
              }"
              @click="state.packageKey = pkg.key">
              <p
class="text-[13px] font-semibold mb-0.5"
                :style="{ color: state.packageKey === pkg.key ? 'var(--color-accent)' : 'var(--color-text)' }">
                {{ pkg.name }}
              </p>
              <p class="text-[11px]" style="color: var(--color-text-tertiary);">{{ pkg.tagline }}</p>
              <p v-if="config && config.base_packages[pkg.key]" class="text-[11px] mt-2 font-medium" style="color: var(--color-text-secondary);">
                from {{ fmtMyrExact(config.base_packages[pkg.key]?.min ?? 0) }}
              </p>
            </button>
          </div>
        </Transition>
        <p v-if="!currentCategory" class="text-[12px] mt-1" style="color: var(--color-text-tertiary);">Pick a category above to see packages.</p>
        <p v-if="packageError" class="text-[12px] mt-3" style="color: var(--color-danger);">{{ packageError }}</p>
      </section>

      <!-- Scope details — data-driven (admin-managed service_scope_fields), rendered
           by type: sliders left, switches right, select chip-pickers full-width below. -->
      <Transition name="tab" mode="out-in">
        <section v-if="state.categoryKey && state.packageKey && visibleFields.length" :key="`scope-${state.categoryKey}`">
          <p class="quote-label mb-4">Scope details</p>

          <div v-if="sliderFields.length || toggleFields.length" class="grid sm:grid-cols-2 gap-x-10 gap-y-6 items-start">
            <!-- Sliders (left) -->
            <div v-if="sliderFields.length" class="space-y-5">
              <div v-for="f in sliderFields" :key="f.field_key" class="space-y-2">
                <label class="quote-label">{{ f.label }}: <strong style="color:var(--color-text)">{{ state.scopeValues[f.field_key] }}</strong></label>
                <input
v-model.number="state.scopeValues[f.field_key]" type="range"
                  :min="f.config.min ?? 1" :max="f.config.max ?? 10" class="quote-range w-full" >
              </div>
            </div>
            <!-- Switches (right) -->
            <div v-if="toggleFields.length" class="space-y-4 sm:pt-1">
              <label v-for="f in toggleFields" :key="f.field_key" class="quote-toggle">
                <input v-model="state.scopeValues[f.field_key]" type="checkbox" class="sr-only" >
                <span class="quote-toggle-track" :class="{ active: state.scopeValues[f.field_key] }"/>
                <span class="text-[13px]" style="color: var(--color-text);">{{ f.label }}</span>
              </label>
            </div>
          </div>

          <!-- Selects (full-width chip pickers) -->
          <div v-for="f in selectFields" :key="f.field_key" class="space-y-1.5 mt-6">
            <label class="quote-label">{{ f.label }}</label>
            <div class="flex flex-wrap gap-2">
              <button
v-for="opt in f.config.options ?? []" :key="opt.value" type="button"
                class="text-[12px] px-3.5 py-1.5 rounded-full border transition-all"
                :style="{
                  borderColor: state.scopeValues[f.field_key] === opt.value ? 'var(--color-accent)' : 'var(--color-border)',
                  background: state.scopeValues[f.field_key] === opt.value ? 'var(--color-accent-soft)' : 'transparent',
                  color: state.scopeValues[f.field_key] === opt.value ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                }"
                @click="state.scopeValues[f.field_key] = opt.value">
                {{ opt.label }}
              </button>
            </div>
          </div>
        </section>
      </Transition>

      <!-- Add-ons -->
      <section v-if="state.packageKey && config">
        <p class="quote-label mb-4">Add-ons</p>
        <div class="grid sm:grid-cols-2 gap-3">
          <button
v-for="[key, addon] in Object.entries(config.addons)" :key="key" type="button"
            class="flex items-center justify-between rounded-xl border px-4 py-3.5 text-left transition-all"
            :style="{
              borderColor: state.addonKeys.includes(key) ? 'var(--color-accent)' : 'var(--color-border)',
              background: state.addonKeys.includes(key) ? 'var(--color-accent-soft)' : 'var(--color-bg-elevated)',
            }"
            @click="toggleInArray(state.addonKeys, key)">
            <p
class="text-[13px] font-medium"
              :style="{ color: state.addonKeys.includes(key) ? 'var(--color-accent)' : 'var(--color-text)' }">
              {{ addon.label }}
            </p>
            <p
class="text-[12px] font-semibold shrink-0 ml-3"
              :style="{ color: state.addonKeys.includes(key) ? 'var(--color-accent)' : 'var(--color-text-secondary)' }">
              +{{ fmtMyrExact(addon.amount) }}
            </p>
          </button>
        </div>
      </section>

      <!-- Rush -->
      <section v-if="state.packageKey">
        <label class="quote-toggle flex items-center gap-3">
          <input v-model="state.rush" type="checkbox" class="sr-only" >
          <span class="quote-toggle-track" :class="{ active: state.rush }"/>
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
