<script setup lang="ts">
import { serviceCategories } from '~/data/services'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })
useHead({ title: 'Services — Admin' })

const totalPackages = serviceCategories.reduce((sum, c) => sum + c.packages.length, 0)
const featuredPackages = serviceCategories.reduce(
  (sum, c) => sum + c.packages.filter(p => p.featured).length,
  0,
)

function fmtPrice(min: number, max: number | null) {
  const f = (n: number) => n >= 1000 ? `RM ${(n / 1000).toFixed(0)}k` : `RM ${n}`
  if (max === null) return `${f(min)}+`
  return `${f(min)} – ${f(max)}`
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-10 pb-32">
    <div class="mb-6">
      <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Admin</p>
      <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Services</h1>
      <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
        {{ serviceCategories.length }} categories · {{ totalPackages }} packages · {{ featuredPackages }} featured
      </p>
    </div>

    <!-- Phase banner -->
    <div
      class="mb-8 rounded-xl border p-4 flex items-start gap-3"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-accent-soft)' }"
    >
      <UIcon name="i-lucide-info" class="size-4 mt-0.5 shrink-0" :style="{ color: 'var(--color-accent)' }" />
      <div>
        <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-accent)' }">Read-only preview (Phase C)</p>
        <p class="text-[12px] mt-0.5" :style="{ color: 'var(--color-text-secondary)' }">
          Currently sourced from <span class="font-mono">app/data/services.ts</span>. CMS editing wires up after migrating these into <span class="font-mono">service_categories</span> and <span class="font-mono">service_packages</span> tables.
        </p>
      </div>
    </div>

    <div class="space-y-8">
      <section
        v-for="cat in serviceCategories"
        :key="cat.id"
        class="rounded-2xl border overflow-hidden"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
      >
        <header
          class="flex items-center gap-3 px-5 py-4 border-b"
          :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-secondary)' }"
        >
          <div
            class="size-9 rounded-xl inline-flex items-center justify-center"
            :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
          >
            <UIcon :name="cat.icon" class="size-4" />
          </div>
          <div class="min-w-0">
            <p class="text-[14px] font-semibold tracking-tight" :style="{ color: 'var(--color-text)' }">{{ cat.label }}</p>
            <p class="text-[12px] truncate" :style="{ color: 'var(--color-text-secondary)' }">{{ cat.description }}</p>
          </div>
          <span
            class="ml-auto text-[11px] font-semibold px-2 py-0.5 rounded-full"
            :style="{ color: 'var(--color-text-tertiary)', background: 'var(--color-bg)' }"
          >
            {{ cat.packages.length }} packages
          </span>
        </header>

        <ul>
          <li
            v-for="pkg in cat.packages"
            :key="pkg.id"
            class="flex items-center gap-4 px-5 py-3.5 border-b last:border-b-0"
            :style="{ borderColor: 'var(--color-border)' }"
          >
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2">
                <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-text)' }">{{ pkg.name }}</p>
                <span
                  v-if="pkg.featured"
                  class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded"
                  :style="{ color: 'var(--color-accent)', background: 'var(--color-accent-soft)' }"
                >
                  Featured
                </span>
              </div>
              <p class="text-[12px] mt-0.5 truncate" :style="{ color: 'var(--color-text-secondary)' }">{{ pkg.tagline }}</p>
            </div>
            <div class="text-right shrink-0">
              <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-text)' }">{{ fmtPrice(pkg.priceMin, pkg.priceMax) }}</p>
              <p class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ pkg.duration }}</p>
            </div>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>
