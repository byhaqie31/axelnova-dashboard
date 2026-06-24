<script setup lang="ts">
/**
 * Optional "detailed proposal" blocks that extend a standard quote — "What's
 * included" groups, option cards, and a care plan, plus the detailed header
 * fields (subtitle + client attn/address). Self-contained: it owns its own state,
 * hydrates from an existing `document.payload` via the `initial` prop, and the
 * parent <QuotationBuilder> reads the composed blocks back through the exposed
 * `buildBlocks()`. The scope/pricing (line items → "Scope of work" section), terms,
 * and deposit stay in the parent.
 */
const props = defineProps<{
  initial?: Record<string, any> | null
}>()

interface IncGroup { eyebrow: string; itemsText: string; columns: 1 | 2; note: string }
interface OptCard { badge: string; accent: boolean; title: string; sub: string; price: number | null; priceWas: number | null; priceNote: string }
interface CareRow { label: string; detail: string; price: number | null; period: string }

const subtitle = ref('Website quotation')
const attn = ref('')
const address = ref('')
const included = ref<IncGroup[]>([])
const optTitle = ref('Package options')
const optPromo = ref('')
const options = ref<OptCard[]>([])
const careTitle = ref('Care & support')
const careNote = ref('')
const care = ref<CareRow[]>([])

function addIncluded() { included.value.push({ eyebrow: '', itemsText: '', columns: 1, note: '' }) }
function removeIncluded(i: number) { included.value.splice(i, 1) }

function addOption() {
  const letter = String.fromCharCode(65 + options.value.length)
  options.value.push({ badge: `OPTION ${letter}`, accent: options.value.length === 0, title: '', sub: '', price: null, priceWas: null, priceNote: 'one-time' })
}
function removeOption(i: number) { options.value.splice(i, 1) }

function addCare() { care.value.push({ label: '', detail: '', price: null, period: 'month' }) }
function removeCare(i: number) { care.value.splice(i, 1) }

function hydrate(payload: Record<string, any>) {
  subtitle.value = payload.subtitle ?? 'Website quotation'
  attn.value = payload.client?.attn ?? ''
  address.value = payload.client?.address ?? ''
  included.value = (payload.included ?? []).map((g: any) => ({
    eyebrow: g.eyebrow ?? '',
    itemsText: (g.items ?? []).join('\n'),
    columns: g.columns === 2 ? 2 : 1,
    note: g.note ?? '',
  }))
  optTitle.value = payload.options?.title ?? 'Package options'
  optPromo.value = payload.options?.promo ?? ''
  options.value = (payload.options?.cards ?? []).map((c: any) => ({
    badge: c.badge ?? 'OPTION',
    accent: !!c.accent,
    title: c.title ?? '',
    sub: c.sub ?? '',
    price: c.price ?? null,
    priceWas: c.priceWas ?? null,
    priceNote: c.priceNote ?? '',
  }))
  careTitle.value = payload.care?.title ?? 'Care & support'
  careNote.value = payload.care?.note ?? ''
  care.value = (payload.care?.rows ?? []).map((r: any) => ({
    label: r.label ?? '',
    detail: r.detail ?? '',
    price: r.price ?? null,
    period: r.period ?? '',
  }))
}

if (props.initial) hydrate(props.initial)

/** Compose the detailed-only payload fragments the parent merges into document.payload. */
function buildBlocks(): Record<string, any> {
  const inc = included.value
    .filter(g => g.itemsText.trim())
    .map(g => ({
      ...(g.eyebrow.trim() ? { eyebrow: g.eyebrow } : {}),
      items: g.itemsText.split('\n').map(x => x.trim()).filter(Boolean),
      columns: g.columns,
      ...(g.note.trim() ? { note: g.note } : {}),
    }))

  const cards = options.value
    .filter(c => c.title.trim())
    .map(c => ({
      badge: c.badge || 'OPTION',
      ...(c.accent ? { accent: true } : {}),
      title: c.title,
      ...(c.sub.trim() ? { sub: c.sub } : {}),
      price: Number(c.price) || 0,
      ...(c.priceWas != null && String(c.priceWas) !== '' ? { priceWas: Number(c.priceWas) } : {}),
      ...(c.priceNote.trim() ? { priceNote: c.priceNote } : {}),
    }))

  const careRows = care.value
    .filter(r => r.label.trim())
    .map(r => ({
      label: r.label,
      detail: r.detail || '',
      price: Number(r.price) || 0,
      ...(r.period.trim() ? { period: r.period } : {}),
    }))

  return {
    subtitle: subtitle.value || null,
    ...((attn.value || address.value) ? { client: { attn: attn.value || null, address: address.value || null } } : {}),
    ...(inc.length ? { included: inc } : {}),
    ...(cards.length ? { options: { title: optTitle.value || 'Package options', ...(optPromo.value.trim() ? { promo: optPromo.value } : {}), cards } } : {}),
    ...(careRows.length ? { care: { title: careTitle.value || 'Care & support', rows: careRows, ...(careNote.value.trim() ? { note: careNote.value } : {}) } } : {}),
  }
}

defineExpose({ buildBlocks })

const fieldStyle = { borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }
</script>

<template>
  <div class="space-y-6">
    <!-- Header fields -->
    <div class="grid sm:grid-cols-2 gap-4">
      <div class="space-y-1.5">
        <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Subtitle</label>
        <input v-model="subtitle" type="text" placeholder="e.g. Website quotation" class="contact-input w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }">
      </div>
      <div class="space-y-1.5">
        <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Attn (optional)</label>
        <input v-model="attn" type="text" placeholder="e.g. Daniel Foong, Marketing Lead" class="contact-input w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }">
      </div>
    </div>
    <div class="space-y-1.5">
      <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Address (optional)</label>
      <input v-model="address" type="text" class="contact-input w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }">
    </div>

    <!-- What's included -->
    <div class="space-y-3 pt-2 border-t" :style="{ borderColor: 'var(--color-border)' }">
      <div class="flex items-start justify-between gap-3">
        <div class="space-y-0.5">
          <label class="text-[12px] font-medium block" style="color: var(--color-text-secondary);">“What's included” groups</label>
          <p class="text-[11px]" style="color: var(--color-text-tertiary);">Tick-list groups shown on the quotation.</p>
        </div>
        <button type="button" class="text-[12px] shrink-0 mt-0.5" style="color: var(--color-accent);" @click="addIncluded">+ Add group</button>
      </div>
      <button v-if="!included.length" type="button" class="w-full rounded-xl border border-dashed px-4 py-5 text-center text-[12px] transition-colors hover:border-(--color-accent)" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-tertiary)' }" @click="addIncluded">
        No groups yet. <span class="font-medium" style="color: var(--color-text-secondary);">Add a group</span> to list what's included, like a “BASIC SEO” set with bullet points.
      </button>
      <div v-for="(g, gi) in included" :key="gi" class="rounded-xl border p-3 space-y-2" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
        <div class="flex items-center gap-2">
          <input v-model="g.eyebrow" type="text" placeholder="Eyebrow (optional, e.g. BASIC SEO)" class="contact-input flex-1 min-w-0 text-[12px]" :style="fieldStyle">
          <select v-model.number="g.columns" class="contact-input shrink-0 text-[12px]" :style="{ ...fieldStyle, width: '7rem' }">
            <option :value="1">1 column</option>
            <option :value="2">2 columns</option>
          </select>
          <button type="button" class="size-9 rounded-lg flex items-center justify-center shrink-0 transition-colors hover:bg-(--color-bg-secondary)" :style="{ color: 'var(--color-danger)' }" aria-label="Remove group" @click="removeIncluded(gi)">
            <UIcon name="i-lucide-trash-2" class="size-4" />
          </button>
        </div>
        <textarea v-model="g.itemsText" rows="3" placeholder="One bullet per line…" class="contact-input resize-none w-full text-[12px]" :style="fieldStyle" />
        <input v-model="g.note" type="text" placeholder="Group note (optional)" class="contact-input w-full text-[12px]" :style="fieldStyle">
      </div>
    </div>

    <!-- Option cards -->
    <div class="space-y-3 pt-2 border-t" :style="{ borderColor: 'var(--color-border)' }">
      <div class="flex items-start justify-between gap-3">
        <div class="space-y-0.5">
          <label class="text-[12px] font-medium block" style="color: var(--color-text-secondary);">Option cards</label>
          <p class="text-[11px]" style="color: var(--color-text-tertiary);">Side-by-side package choices the client picks between.</p>
        </div>
        <button type="button" class="text-[12px] shrink-0 mt-0.5" style="color: var(--color-accent);" @click="addOption">+ Add option</button>
      </div>
      <button v-if="!options.length" type="button" class="w-full rounded-xl border border-dashed px-4 py-5 text-center text-[12px] transition-colors hover:border-(--color-accent)" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-tertiary)' }" @click="addOption">
        No options yet. <span class="font-medium" style="color: var(--color-text-secondary);">Add a card</span> to present tiered choices (Option A, Option B).
      </button>
      <div v-if="options.length" class="grid sm:grid-cols-2 gap-2">
        <div class="space-y-1.5">
          <span class="d-label">Options heading</span>
          <input v-model="optTitle" type="text" class="contact-input w-full text-[12px]" :style="fieldStyle">
        </div>
        <div class="space-y-1.5">
          <span class="d-label">Promo pill (optional)</span>
          <input v-model="optPromo" type="text" placeholder="e.g. Launch offer" class="contact-input w-full text-[12px]" :style="fieldStyle">
        </div>
      </div>
      <div v-for="(c, ci) in options" :key="ci" class="rounded-xl border p-3 space-y-2" :style="{ borderColor: c.accent ? 'var(--color-accent)' : 'var(--color-border)', background: 'var(--color-bg)' }">
        <div class="flex items-center gap-2">
          <input v-model="c.badge" type="text" placeholder="OPTION A" class="contact-input shrink-0 text-[11px] font-semibold uppercase tracking-wider" :style="{ ...fieldStyle, width: '8rem' }">
          <input v-model="c.title" type="text" placeholder="Option title" class="contact-input flex-1 min-w-0 text-[13px] font-medium" :style="fieldStyle">
          <button type="button" class="size-9 rounded-lg flex items-center justify-center shrink-0 transition-colors hover:bg-(--color-bg-secondary)" :style="{ color: 'var(--color-danger)' }" aria-label="Remove option" @click="removeOption(ci)">
            <UIcon name="i-lucide-trash-2" class="size-4" />
          </button>
        </div>
        <input v-model="c.sub" type="text" placeholder="Sub line (optional)" class="contact-input w-full text-[12px]" :style="fieldStyle">
        <div class="flex flex-wrap items-end gap-2">
          <div class="w-32">
            <span class="d-label">Price</span>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[12px] pointer-events-none" style="color: var(--color-text-tertiary);">RM</span>
              <input v-model.number="c.price" type="number" min="0" step="50" class="contact-input w-full text-[13px] pl-9 text-right" :style="fieldStyle">
            </div>
          </div>
          <div class="w-32">
            <span class="d-label">Was (optional)</span>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[12px] pointer-events-none" style="color: var(--color-text-tertiary);">RM</span>
              <input v-model.number="c.priceWas" type="number" min="0" step="50" class="contact-input w-full text-[13px] pl-9 text-right" :style="fieldStyle">
            </div>
          </div>
          <div class="flex-1 min-w-28">
            <span class="d-label">Price note</span>
            <input v-model="c.priceNote" type="text" placeholder="one-time" class="contact-input w-full text-[12px]" :style="fieldStyle">
          </div>
          <label class="inline-flex items-center gap-1.5 text-[12px] pb-2.5" style="color: var(--color-text-secondary);">
            <input v-model="c.accent" type="checkbox"> Recommended
          </label>
        </div>
      </div>
    </div>

    <!-- Care plan -->
    <div class="space-y-3 pt-2 border-t" :style="{ borderColor: 'var(--color-border)' }">
      <div class="flex items-start justify-between gap-3">
        <div class="space-y-0.5">
          <label class="text-[12px] font-medium block" style="color: var(--color-text-secondary);">Care plan</label>
          <p class="text-[11px]" style="color: var(--color-text-tertiary);">Optional ongoing support tiers listed after the quote.</p>
        </div>
        <button type="button" class="text-[12px] shrink-0 mt-0.5" style="color: var(--color-accent);" @click="addCare">+ Add plan row</button>
      </div>
      <button v-if="!care.length" type="button" class="w-full rounded-xl border border-dashed px-4 py-5 text-center text-[12px] transition-colors hover:border-(--color-accent)" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-tertiary)' }" @click="addCare">
        No care plan yet. <span class="font-medium" style="color: var(--color-text-secondary);">Add a row</span> for monthly or yearly support.
      </button>
      <input v-if="care.length" v-model="careTitle" type="text" placeholder="Care section title" class="contact-input w-full text-[12px]" :style="fieldStyle">
      <div v-for="(r, ri) in care" :key="ri" class="flex flex-wrap items-end gap-2 rounded-xl border p-2.5" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
        <div class="w-36">
          <span class="d-label">Plan</span>
          <input v-model="r.label" type="text" placeholder="Basic" class="contact-input w-full text-[13px]" :style="fieldStyle">
        </div>
        <div class="flex-1 min-w-40">
          <span class="d-label">Detail</span>
          <input v-model="r.detail" type="text" placeholder="Hosting + updates" class="contact-input w-full text-[12px]" :style="fieldStyle">
        </div>
        <div class="w-28">
          <span class="d-label">Price</span>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[12px] pointer-events-none" style="color: var(--color-text-tertiary);">RM</span>
            <input v-model.number="r.price" type="number" min="0" step="10" class="contact-input w-full text-[13px] pl-9 text-right" :style="fieldStyle">
          </div>
        </div>
        <div class="w-24">
          <span class="d-label">Per</span>
          <select v-model="r.period" class="contact-input w-full text-[12px]" :style="fieldStyle">
            <option value="">—</option>
            <option value="month">month</option>
            <option value="year">year</option>
          </select>
        </div>
        <button type="button" class="size-9 rounded-lg flex items-center justify-center shrink-0 transition-colors hover:bg-(--color-bg-secondary)" :style="{ color: 'var(--color-danger)' }" aria-label="Remove plan row" @click="removeCare(ri)">
          <UIcon name="i-lucide-x" class="size-4" />
        </button>
      </div>
      <input v-if="care.length" v-model="careNote" type="text" placeholder="Care note (optional)" class="contact-input w-full text-[12px]" :style="fieldStyle">
    </div>
  </div>
</template>

<style scoped>
.d-label {
  display: block;
  margin-bottom: 4px;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-tertiary);
}
</style>
