<script setup lang="ts">
// Read-only render of a detailed quotation's composed document (the same content
// the client sees in the PDF), arranged like the builder but not editable.
const props = defineProps<{ payload: Record<string, any> }>()

const p = computed(() => props.payload ?? {})

function fmtRm(n: unknown) {
  return `RM ${(Number(n) || 0).toLocaleString()}`
}

const cardStyle = { background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }
</script>

<template>
  <div class="space-y-6">
    <!-- Hero -->
    <div v-if="p.project || p.intro" class="rounded-2xl border p-6" :style="cardStyle">
      <p v-if="p.project" class="text-[11px] font-semibold uppercase tracking-widest mb-2" style="color: var(--color-accent);">Prepared for</p>
      <h2 v-if="p.project" class="text-[22px] font-bold tracking-tight" style="color: var(--color-text);">{{ p.project }}</h2>
      <p v-if="p.subtitle" class="text-[14px] mt-1" style="color: var(--color-text-secondary);">{{ p.subtitle }}</p>
      <p v-if="p.intro" class="text-[13px] leading-relaxed mt-3" style="color: var(--color-text-secondary);">{{ p.intro }}</p>
    </div>

    <!-- Scope sections -->
    <div v-for="(s, si) in p.sections" :key="`sec-${si}`" class="rounded-2xl border p-6" :style="cardStyle">
      <div class="flex items-center gap-2.5 mb-4">
        <span class="size-2 rounded-sm shrink-0" style="background: var(--color-accent);" />
        <p class="text-[15px] font-semibold tracking-tight" style="color: var(--color-text);">{{ s.title }}</p>
      </div>
      <div class="divide-y" style="border-color: var(--color-border);">
        <div v-for="(r, ri) in s.rows" :key="ri" class="flex items-start justify-between gap-4 py-3 first:pt-0">
          <div class="min-w-0">
            <p class="text-[13px] font-medium" style="color: var(--color-text);">{{ r.title }}</p>
            <p v-if="r.detail" class="text-[12px] mt-0.5 leading-snug" style="color: var(--color-text-secondary);">{{ r.detail }}</p>
          </div>
          <span class="text-[13px] font-medium tabular-nums whitespace-nowrap" :style="{ color: r.priceText ? 'var(--color-success)' : 'var(--color-text)' }">
            {{ r.priceText || fmtRm(r.price) }}
          </span>
        </div>
      </div>
      <div v-if="s.totalLabel" class="flex items-center justify-between gap-4 pt-3 mt-1 border-t-2" style="border-color: var(--color-text);">
        <span class="text-[13px] font-semibold" style="color: var(--color-text);">{{ s.totalLabel }}</span>
        <span class="text-[14px] font-bold tabular-nums" style="color: var(--color-accent);">{{ s.totalText || fmtRm(s.total) }}</span>
      </div>
      <p v-if="s.note" class="text-[12px] mt-3 leading-snug" style="color: var(--color-text-tertiary);">{{ s.note }}</p>
    </div>

    <!-- What's included -->
    <div v-if="p.included?.length" class="rounded-2xl border p-6 space-y-5" :style="cardStyle">
      <div v-for="(g, gi) in p.included" :key="`inc-${gi}`">
        <p v-if="g.eyebrow" class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-accent);">{{ g.eyebrow }}</p>
        <ul class="grid gap-2" :class="g.columns === 2 ? 'sm:grid-cols-2' : ''">
          <li v-for="(it, ii) in g.items" :key="ii" class="flex items-start gap-2 text-[13px]" style="color: var(--color-text-secondary);">
            <span class="size-1.5 rounded-full mt-1.5 shrink-0" style="background: var(--color-accent);" />
            <span>{{ it }}</span>
          </li>
        </ul>
        <p v-if="g.note" class="text-[12px] mt-2 leading-snug" style="color: var(--color-text-tertiary);">{{ g.note }}</p>
      </div>
    </div>

    <!-- Option cards -->
    <div v-if="p.options?.cards?.length" class="rounded-2xl border p-6" :style="cardStyle">
      <p class="text-[15px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">{{ p.options.title || 'Package options' }}</p>
      <div class="grid sm:grid-cols-2 gap-3">
        <div v-for="(c, ci) in p.options.cards" :key="`opt-${ci}`" class="rounded-xl border p-4"
          :style="{ borderColor: c.accent ? 'var(--color-accent)' : 'var(--color-border)', background: 'var(--color-bg)' }">
          <p class="text-[11px] font-semibold uppercase tracking-wider" :style="{ color: c.accent ? 'var(--color-accent)' : 'var(--color-text-tertiary)' }">{{ c.badge }}</p>
          <p class="text-[14px] font-semibold mt-1.5" style="color: var(--color-text);">{{ c.title }}</p>
          <p v-if="c.sub" class="text-[12px] mt-1 leading-snug" style="color: var(--color-text-secondary);">{{ c.sub }}</p>
          <div class="flex items-baseline gap-2 mt-3">
            <span v-if="c.priceWas != null" class="text-[12px] line-through tabular-nums" style="color: var(--color-text-tertiary);">{{ fmtRm(c.priceWas) }}</span>
            <span class="text-[18px] font-bold tabular-nums" :style="{ color: c.accent ? 'var(--color-accent)' : 'var(--color-text)' }">{{ fmtRm(c.price) }}</span>
            <span v-if="c.priceNote" class="text-[11px]" style="color: var(--color-text-tertiary);">{{ c.priceNote }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Care plan -->
    <div v-if="p.care?.rows?.length" class="rounded-2xl border p-6" :style="cardStyle">
      <p class="text-[15px] font-semibold tracking-tight mb-1" style="color: var(--color-text);">{{ p.care.title || 'Care & support' }}</p>
      <p v-if="p.care.intro" class="text-[13px] leading-relaxed mb-3 mt-2" style="color: var(--color-text-secondary);">{{ p.care.intro }}</p>
      <div class="divide-y mt-3" style="border-color: var(--color-border);">
        <div v-for="(r, ri) in p.care.rows" :key="ri" class="flex items-center justify-between gap-4 py-3 first:pt-0">
          <div class="min-w-0">
            <p class="text-[13px] font-medium" style="color: var(--color-text);">{{ r.label }}</p>
            <p v-if="r.detail" class="text-[12px] mt-0.5" style="color: var(--color-text-secondary);">{{ r.detail }}</p>
          </div>
          <span class="text-[13px] font-medium tabular-nums whitespace-nowrap" style="color: var(--color-text-secondary);">
            {{ fmtRm(r.price) }}<span v-if="r.period" style="color: var(--color-text-tertiary);"> / {{ r.period }}</span>
          </span>
        </div>
      </div>
      <p v-if="p.care.note" class="text-[12px] mt-3" style="color: var(--color-text-tertiary);">{{ p.care.note }}</p>
    </div>

    <!-- Summary + deposit/balance -->
    <div v-if="p.summary?.rows?.length || p.panels?.length" class="rounded-2xl border p-6" :style="cardStyle">
      <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Summary</p>
      <div v-if="p.summary?.rows?.length" class="divide-y" style="border-color: var(--color-border);">
        <div v-for="(r, ri) in p.summary.rows" :key="ri" class="flex items-center justify-between gap-4 py-2.5"
          :class="r.total ? 'border-t-2 mt-1 pt-3' : ''" :style="r.total ? { borderColor: 'var(--color-text)' } : {}">
          <span class="text-[13px]" :class="r.total ? 'font-semibold' : ''" style="color: var(--color-text);">{{ r.label }}</span>
          <span class="text-[13px] font-medium tabular-nums" :class="r.total ? 'text-[15px] font-bold' : ''"
            :style="{ color: (r.total || r.red) ? 'var(--color-accent)' : 'var(--color-text)' }">{{ r.priceText || fmtRm(r.price) }}</span>
        </div>
      </div>
      <div v-if="p.panels?.length" class="grid sm:grid-cols-2 gap-3 mt-4">
        <div v-for="(pl, pi) in p.panels" :key="pi" class="rounded-xl border p-4"
          :style="{ borderColor: pl.accent ? 'var(--color-accent)' : 'var(--color-border)', background: 'var(--color-bg)' }">
          <p class="text-[18px] font-bold tabular-nums" :style="{ color: pl.accent ? 'var(--color-accent)' : 'var(--color-text)' }">{{ fmtRm(pl.value) }}</p>
          <p class="text-[11px] font-semibold uppercase tracking-wider mt-1" style="color: var(--color-text-tertiary);">{{ pl.label }}</p>
          <p v-if="pl.note" class="text-[11px] mt-2 leading-snug" style="color: var(--color-text-tertiary);">{{ pl.note }}</p>
        </div>
      </div>
    </div>

    <!-- Payment terms -->
    <div v-if="p.paymentTerms?.items?.length" class="rounded-2xl border p-6" :style="cardStyle">
      <p class="text-[15px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">{{ p.paymentTerms.title || 'Payment terms' }}</p>
      <ul class="space-y-2">
        <li v-for="(t, ti) in p.paymentTerms.items" :key="ti" class="flex items-start gap-2 text-[13px]" style="color: var(--color-text-secondary);">
          <span class="size-1.5 rounded-full mt-1.5 shrink-0" style="background: var(--color-accent);" />
          <span>{{ t }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>
