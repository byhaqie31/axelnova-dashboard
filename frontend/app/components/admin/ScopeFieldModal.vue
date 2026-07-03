<script setup lang="ts">
// Right-side slide-over for creating / editing a category scope field. Opens over
// the category page (no navigation) — lighter + faster than a separate route.

interface Opt { value: string; label: string; amount: number }
interface ScopeFieldRow {
  id: number
  field_key: string
  label: string
  type: 'slider' | 'select' | 'toggle'
  applies_to: string[]
  config: Record<string, any>
  sort_order: number
  active: boolean
}

const props = defineProps<{
  open: boolean
  /** null = create; a row = edit. */
  field?: ScopeFieldRow | null
  categoryId: number
}>()
const emit = defineEmits<{ close: []; saved: [] }>()

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

const isEdit = computed(() => !!props.field?.id)

const types = [
  { value: 'slider', label: 'Slider', icon: 'i-lucide-sliders-horizontal', hint: 'A numeric count, priced per unit beyond a free threshold.' },
  { value: 'toggle', label: 'Toggle', icon: 'i-lucide-toggle-right', hint: 'An on/off option with a flat price when on.' },
  { value: 'select', label: 'Select', icon: 'i-lucide-list', hint: 'A choice of options, each with its own price.' },
] as const

const form = reactive({
  field_key: '',
  label: '',
  type: 'slider' as 'slider' | 'select' | 'toggle',
  applies_to: [] as string[],
  sort_order: 0,
  active: true,
  s_min: 1, s_max: 10, s_default: 1, s_unit: '', s_free_threshold: 0, s_price_per_unit: 0,
  t_amount: 0, t_default: false,
  sel_default: '',
  sel_options: [{ value: '', label: '', amount: 0 }] as Opt[],
})

interface PackageOpt { key: string; name: string }
const packages = ref<PackageOpt[]>([])
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const message = ref('')

const typeHint = computed(() => types.find(t => t.value === form.type)?.hint ?? '')

// Repopulate + reload packages each time the drawer opens.
watch(() => props.open, (open) => {
  if (!open) return
  errors.value = {}
  message.value = ''
  populate(props.field ?? null)
  loadPackages()
})

watch(() => form.field_key, (v) => {
  const cleaned = v.toLowerCase().replace(/[^a-z0-9_]/g, '_').replace(/_+/g, '_')
  if (cleaned !== v) form.field_key = cleaned
})

onKeyStroke('Escape', () => { if (props.open) emit('close') })

function populate(f: ScopeFieldRow | null) {
  Object.assign(form, {
    field_key: f?.field_key ?? '',
    label: f?.label ?? '',
    type: f?.type ?? 'slider',
    applies_to: [...(f?.applies_to ?? [])],
    sort_order: f?.sort_order ?? 0,
    active: f?.active ?? true,
    s_min: 1, s_max: 10, s_default: 1, s_unit: '', s_free_threshold: 0, s_price_per_unit: 0,
    t_amount: 0, t_default: false,
    sel_default: '',
    sel_options: [{ value: '', label: '', amount: 0 }],
  })
  const c = f?.config ?? {}
  if (f?.type === 'slider') {
    form.s_min = c.min ?? 1; form.s_max = c.max ?? 10; form.s_default = c.default ?? c.min ?? 1
    form.s_unit = c.unit ?? ''; form.s_free_threshold = c.free_threshold ?? 0; form.s_price_per_unit = c.price_per_unit ?? 0
  }
  else if (f?.type === 'toggle') {
    form.t_amount = c.amount ?? 0; form.t_default = !!c.default
  }
  else if (f?.type === 'select') {
    form.sel_default = c.default ?? ''
    form.sel_options = (c.options ?? []).length ? c.options.map((o: Opt) => ({ ...o })) : [{ value: '', label: '', amount: 0 }]
  }
}

function togglePackage(key: string) {
  const i = form.applies_to.indexOf(key)
  if (i === -1) form.applies_to.push(key)
  else form.applies_to.splice(i, 1)
}
function addOption() { form.sel_options.push({ value: '', label: '', amount: 0 }) }
function removeOption(i: number) { form.sel_options.splice(i, 1) }

function buildConfig(): Record<string, unknown> {
  if (form.type === 'slider') {
    return {
      min: form.s_min, max: form.s_max, default: form.s_default,
      unit: form.s_unit, free_threshold: form.s_free_threshold, price_per_unit: form.s_price_per_unit,
    }
  }
  if (form.type === 'toggle') {
    return { amount: form.t_amount, default: form.t_default }
  }
  const options = form.sel_options.filter(o => o.value.trim())
  return { default: form.sel_default || options[0]?.value || '', options }
}

async function loadPackages() {
  if (!props.categoryId) { packages.value = []; return }
  try {
    const res = await apiFetch<{ data: any[] }>(`/api/v1/admin/service-packages?service_category_id=${props.categoryId}`)
    packages.value = res.data.filter(p => p.quote_key?.package).map(p => ({ key: p.quote_key.package as string, name: p.name as string }))
  }
  catch {
    packages.value = []
  }
}

async function save() {
  saving.value = true
  errors.value = {}
  message.value = ''
  const body = {
    service_category_id: props.categoryId,
    field_key: form.field_key,
    label: form.label,
    type: form.type,
    applies_to: form.applies_to,
    sort_order: form.sort_order,
    active: form.active,
    config: buildConfig(),
  }
  try {
    if (isEdit.value) {
      await apiFetch(`/api/v1/admin/service-scope-fields/${props.field!.id}`, { method: 'PUT', body })
    }
    else {
      await apiFetch('/api/v1/admin/service-scope-fields', { method: 'POST', body })
    }
    toast.success(isEdit.value ? 'Scope field saved' : 'Scope field created', `“${form.label}” is up to date.`)
    emit('saved')
    emit('close')
  }
  catch (e: any) {
    if (e?.data?.errors) errors.value = e.data.errors
    message.value = e?.data?.message ?? 'Failed to save.'
    toast.error('Couldn’t save scope field', message.value)
  }
  finally {
    saving.value = false
  }
}

function err(key: string): string | null {
  return errors.value[key]?.[0] ?? null
}

const fieldStyle = { borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }
</script>

<template>
  <Transition name="scope-drawer">
    <div v-if="open" class="fixed inset-0 z-50 flex justify-end">
      <button class="absolute inset-0 cursor-default" style="background: rgba(0,0,0,0.4); backdrop-filter: blur(2px);" aria-label="Close" @click="emit('close')" />

      <div
class="scope-drawer-panel relative h-full w-full max-w-md overflow-y-auto border-l"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }">
        <div
class="sticky top-0 z-10 flex items-center justify-between px-6 py-4 border-b"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[16px] font-semibold tracking-tight" style="color: var(--color-text);">{{ isEdit ? 'Edit scope field' : 'New scope field' }}</p>
          <button type="button" class="size-8 rounded-lg flex items-center justify-center transition-colors hover:bg-(--color-bg-secondary)" style="color: var(--color-text-tertiary);" aria-label="Close" @click="emit('close')">
            <UIcon name="i-lucide-x" class="size-4" />
          </button>
        </div>

        <form class="p-6 space-y-5" @submit.prevent="save">
          <!-- Type -->
          <div>
            <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Type *</label>
            <div class="grid grid-cols-3 gap-2">
              <button
v-for="t in types" :key="t.value" type="button" class="flex flex-col items-center gap-1.5 rounded-xl border px-3 py-3 transition-all"
                :style="form.type === t.value
                  ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
                  : { borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }"
                @click="form.type = t.value">
                <UIcon :name="t.icon" class="size-5" />
                <span class="text-[12px] font-medium">{{ t.label }}</span>
              </button>
            </div>
            <p class="mt-1.5 text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ typeHint }}</p>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Key *</label>
              <input v-model="form.field_key" type="text" required placeholder="extra_page" class="contact-input w-full font-mono text-[12px]" :style="fieldStyle" >
              <p v-if="err('field_key')" class="mt-1 text-[11px]" :style="{ color: 'var(--color-danger)' }">{{ err('field_key') }}</p>
            </div>
            <div>
              <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Label *</label>
              <input v-model="form.label" type="text" required placeholder="Number of pages" class="contact-input w-full" :style="fieldStyle" >
              <p v-if="err('label')" class="mt-1 text-[11px]" :style="{ color: 'var(--color-danger)' }">{{ err('label') }}</p>
            </div>
          </div>

          <!-- Slider config -->
          <div v-if="form.type === 'slider'" class="rounded-xl border p-4 space-y-4" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
            <p class="text-[12px] font-semibold" :style="{ color: 'var(--color-text)' }">Slider settings</p>
            <div class="grid grid-cols-3 gap-3">
              <div><label class="text-[11px] block mb-1" :style="{ color: 'var(--color-text-secondary)' }">Min</label><input v-model.number="form.s_min" type="number" class="contact-input w-full" :style="fieldStyle" ></div>
              <div><label class="text-[11px] block mb-1" :style="{ color: 'var(--color-text-secondary)' }">Max</label><input v-model.number="form.s_max" type="number" class="contact-input w-full" :style="fieldStyle" ></div>
              <div><label class="text-[11px] block mb-1" :style="{ color: 'var(--color-text-secondary)' }">Default</label><input v-model.number="form.s_default" type="number" class="contact-input w-full" :style="fieldStyle" ></div>
            </div>
            <div class="grid grid-cols-3 gap-3">
              <div><label class="text-[11px] block mb-1" :style="{ color: 'var(--color-text-secondary)' }">Unit</label><input v-model="form.s_unit" type="text" placeholder="page" class="contact-input w-full" :style="fieldStyle" ></div>
              <div><label class="text-[11px] block mb-1" :style="{ color: 'var(--color-text-secondary)' }">Free up to</label><input v-model.number="form.s_free_threshold" type="number" min="0" class="contact-input w-full" :style="fieldStyle" ></div>
              <div><label class="text-[11px] block mb-1" :style="{ color: 'var(--color-text-secondary)' }">RM / extra</label><input v-model.number="form.s_price_per_unit" type="number" min="0" step="50" class="contact-input w-full" :style="fieldStyle" ></div>
            </div>
            <p class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">First {{ form.s_free_threshold }} {{ form.s_unit || 'unit' }}(s) free; each beyond adds RM {{ form.s_price_per_unit || 0 }}. Set RM/extra to 0 to capture scope without pricing.</p>
          </div>

          <!-- Toggle config -->
          <div v-else-if="form.type === 'toggle'" class="rounded-xl border p-4 space-y-3" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
            <p class="text-[12px] font-semibold" :style="{ color: 'var(--color-text)' }">Toggle settings</p>
            <div class="grid grid-cols-2 gap-3 items-end">
              <div><label class="text-[11px] block mb-1" :style="{ color: 'var(--color-text-secondary)' }">Price when on (RM)</label><input v-model.number="form.t_amount" type="number" min="0" step="50" class="contact-input w-full" :style="fieldStyle" ></div>
              <label class="flex items-center gap-2 cursor-pointer pb-2"><input v-model="form.t_default" type="checkbox" class="size-4" ><span class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">On by default</span></label>
            </div>
          </div>

          <!-- Select config -->
          <div v-else class="rounded-xl border p-4 space-y-3" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
            <div class="flex items-center justify-between">
              <p class="text-[12px] font-semibold" :style="{ color: 'var(--color-text)' }">Options</p>
              <button type="button" class="text-[11px] font-medium" :style="{ color: 'var(--color-accent)' }" @click="addOption">+ Add option</button>
            </div>
            <div v-for="(opt, i) in form.sel_options" :key="i" class="flex items-center gap-2">
              <input v-model="form.sel_default" type="radio" :value="opt.value" title="Default" class="size-3.5 shrink-0" >
              <input v-model="opt.value" type="text" placeholder="value" class="contact-input w-20 font-mono text-[12px]" :style="fieldStyle" >
              <input v-model="opt.label" type="text" placeholder="Label" class="contact-input flex-1" :style="fieldStyle" >
              <input v-model.number="opt.amount" type="number" min="0" step="50" placeholder="RM" class="contact-input w-20" :style="fieldStyle" >
              <button type="button" :disabled="form.sel_options.length <= 1" class="shrink-0 size-8 rounded-md inline-flex items-center justify-center transition-opacity disabled:opacity-30" :style="{ color: 'var(--color-danger)' }" aria-label="Remove option" @click="removeOption(i)"><UIcon name="i-lucide-trash-2" class="size-4" /></button>
            </div>
            <p class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">The radio marks the default. Each option adds its RM amount.</p>
          </div>

          <!-- Applies to -->
          <div>
            <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Applies to</label>
            <div v-if="packages.length" class="flex flex-wrap gap-2">
              <button
v-for="p in packages" :key="p.key" type="button" class="text-[12px] px-3 py-1.5 rounded-full border transition-all"
                :style="form.applies_to.includes(p.key)
                  ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
                  : { borderColor: 'var(--color-border)', background: 'transparent', color: 'var(--color-text-secondary)' }"
                @click="togglePackage(p.key)">
                {{ p.name }}
              </button>
            </div>
            <p v-else class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">No quotable packages in this category yet.</p>
            <p class="mt-1.5 text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Leave all unselected to apply to <strong>every</strong> package.</p>
          </div>

          <!-- Active -->
          <button
type="button" class="w-full flex items-center justify-between gap-3 rounded-lg border px-4 py-3 transition-all text-left"
            :style="form.active
              ? { borderColor: 'var(--color-success)', background: 'var(--color-bg)' }
              : { borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
            @click="form.active = !form.active">
            <span>
              <span class="block text-[13px] font-medium" :style="{ color: 'var(--color-text)' }">Active</span>
              <span class="block text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Shown in the quote builder</span>
            </span>
            <span
class="relative inline-block rounded-full transition-colors shrink-0"
              :style="{ background: form.active ? 'var(--color-success)' : '#d1d5db', height: '1.25rem', width: '2.25rem' }">
              <span
class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all"
                :style="{ left: form.active ? '1.125rem' : '0.125rem' }"/>
            </span>
          </button>

          <p v-if="message" class="text-[12px]" :style="{ color: 'var(--color-danger)' }">{{ message }}</p>
        </form>

        <div
class="sticky bottom-0 flex items-center justify-end gap-2 px-6 py-4 border-t"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <button type="button" class="btn-pill btn-pill-ghost text-[13px]" @click="emit('close')">Cancel</button>
          <button type="button" class="btn-pill btn-pill-accent text-[13px]" :disabled="saving" @click="save">
            {{ saving ? 'Saving…' : isEdit ? 'Save changes' : 'Create field' }}
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.scope-drawer-enter-active,
.scope-drawer-leave-active { transition: opacity 0.25s ease; }
.scope-drawer-enter-active .scope-drawer-panel,
.scope-drawer-leave-active .scope-drawer-panel { transition: transform 0.28s cubic-bezier(0.32, 0.72, 0, 1); }
.scope-drawer-enter-from,
.scope-drawer-leave-to { opacity: 0; }
.scope-drawer-enter-from .scope-drawer-panel,
.scope-drawer-leave-to .scope-drawer-panel { transform: translateX(100%); }

@media (prefers-reduced-motion: reduce) {
  .scope-drawer-enter-active,
  .scope-drawer-leave-active,
  .scope-drawer-enter-active .scope-drawer-panel,
  .scope-drawer-leave-active .scope-drawer-panel { transition: none; }
}
</style>
