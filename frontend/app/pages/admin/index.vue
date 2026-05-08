<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })
useHead({ title: 'Dashboard — Admin' })

const { apiFetch } = useAdminAuth()

interface Quotation {
  id: number
  reference_code: string
  name: string
  email: string
  package_key: string | null
  estimate_min_myr: string
  estimate_max_myr: string
  status: string
  submitted_at: string
}

const recent = ref<Quotation[]>([])
const totalQuotations = ref<number | null>(null)
const newQuotations = ref<number | null>(null)
const activeOrders = ref<number | null>(null)
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [recentRes, newRes, ordersRes] = await Promise.all([
      apiFetch<{ data: Quotation[]; meta: { total: number } }>('/api/v1/admin/quotations?include_accepted=1&page=1'),
      apiFetch<{ data: Quotation[]; meta: { total: number } }>('/api/v1/admin/quotations?status=new&page=1'),
      apiFetch<{ data: Quotation[]; meta: { total: number } }>('/api/v1/admin/orders?page=1'),
    ])
    recent.value = recentRes.data.slice(0, 5)
    totalQuotations.value = recentRes.meta.total
    newQuotations.value = newRes.meta.total
    activeOrders.value = ordersRes.meta.total
  }
  catch {
    error.value = 'Failed to load dashboard. Check your session.'
  }
  finally {
    loading.value = false
  }
}

onMounted(load)

function fmtDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short' })
}

function fmtMyr(amount: string | number) {
  const n = Number(amount)
  if (n >= 1000) return `RM ${(n / 1000).toFixed(0)}k`
  return `RM ${n.toLocaleString()}`
}

interface StatTile {
  label: string
  value: string
  hint: string
  icon: string
  pending?: boolean
}

const tiles = computed<StatTile[]>(() => [
  {
    label: 'Total quotations',
    value: totalQuotations.value === null ? '—' : String(totalQuotations.value),
    hint: 'All-time inquiries',
    icon: 'i-lucide-file-text',
  },
  {
    label: 'New (unactioned)',
    value: newQuotations.value === null ? '—' : String(newQuotations.value),
    hint: 'Status = new',
    icon: 'i-lucide-inbox',
  },
  {
    label: 'Active orders',
    value: activeOrders.value === null ? '—' : String(activeOrders.value),
    hint: 'Converted engagements',
    icon: 'i-lucide-package-check',
  },
  {
    label: 'Page views (7d)',
    value: '—',
    hint: 'Wires up in Phase B',
    icon: 'i-lucide-eye',
    pending: true,
  },
])
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <div class="mb-8">
      <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Dashboard</h1>
      <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">Overview of quotations, orders, and traffic.</p>
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <!-- Stat tiles -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-10">
      <div
        v-for="tile in tiles"
        :key="tile.label"
        class="rounded-2xl border p-5"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
      >
        <div class="flex items-start justify-between mb-3">
          <div
            class="size-9 rounded-xl inline-flex items-center justify-center"
            :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
          >
            <UIcon :name="tile.icon" class="size-4" />
          </div>
          <span
            v-if="tile.pending"
            class="text-[10px] font-semibold px-2 py-0.5 rounded-full"
            :style="{ color: 'var(--color-text-tertiary)', background: 'var(--color-bg-secondary)' }"
          >
            Soon
          </span>
        </div>
        <p class="text-[11px] font-semibold uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">{{ tile.label }}</p>
        <p class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">
          <span v-if="loading && !tile.pending" class="opacity-50">—</span>
          <span v-else>{{ tile.value }}</span>
        </p>
        <p class="text-[12px] mt-1" style="color: var(--color-text-secondary);">{{ tile.hint }}</p>
      </div>
    </div>

    <!-- Recent quotations -->
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-[18px] font-semibold tracking-tight" style="color: var(--color-text);">Recent quotations</h2>
      <NuxtLink
        to="/admin/quotations"
        class="text-[12px] font-medium inline-flex items-center gap-1 hover:underline"
        :style="{ color: 'var(--color-accent)' }"
      >
        View all
        <UIcon name="i-lucide-arrow-right" class="size-3.5" />
      </NuxtLink>
    </div>

    <div v-if="loading" class="text-center py-10 text-[13px]" style="color: var(--color-text-secondary);">Loading…</div>

    <div
      v-else-if="!recent.length"
      class="rounded-2xl border p-10 text-center text-[13px]"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }"
    >
      No quotations yet.
    </div>

    <!-- Desktop: table -->
    <div
      v-else
      class="hidden md:block rounded-2xl border overflow-hidden"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
    >
      <div class="overflow-x-auto">
      <table class="w-full text-left">
        <thead>
          <tr style="border-bottom: 1px solid var(--color-border); background: var(--color-bg-secondary);">
            <th v-for="h in ['Reference', 'Name', 'Estimate', 'Status', 'Submitted']" :key="h"
              class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
              {{ h }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="q in recent"
            :key="q.id"
            class="border-b cursor-pointer transition-colors hover:bg-(--color-bg-secondary)"
            style="border-color: var(--color-border);"
            @click="navigateTo(`/admin/quotations/${q.id}`)"
          >
            <td class="px-4 py-3.5">
              <span class="font-mono text-[12px] font-medium" :style="{ color: 'var(--color-accent)' }">{{ q.reference_code }}</span>
            </td>
            <td class="px-4 py-3.5">
              <p class="text-[13px] font-medium" :style="{ color: 'var(--color-text)' }">{{ q.name }}</p>
              <p class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ q.email }}</p>
            </td>
            <td class="px-4 py-3.5">
              <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-text)' }">
                {{ fmtMyr(q.estimate_min_myr) }} – {{ fmtMyr(q.estimate_max_myr) }}
              </p>
            </td>
            <td class="px-4 py-3.5">
              <AdminStatusPill :status="q.status" />
            </td>
            <td class="px-4 py-3.5 text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">
              {{ fmtDate(q.submitted_at) }}
            </td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>

    <!-- Mobile: cards -->
    <div v-if="recent.length" class="md:hidden space-y-2.5">
      <button
        v-for="q in recent"
        :key="q.id"
        type="button"
        class="w-full text-left rounded-xl border p-4 transition-colors hover:bg-(--color-bg-secondary)"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
        @click="navigateTo(`/admin/quotations/${q.id}`)"
      >
        <div class="flex items-start justify-between gap-3 mb-2">
          <span class="font-mono text-[12px] font-medium" :style="{ color: 'var(--color-accent)' }">{{ q.reference_code }}</span>
          <AdminStatusPill :status="q.status" />
        </div>
        <p class="text-[13px] font-medium leading-tight" :style="{ color: 'var(--color-text)' }">{{ q.name }}</p>
        <p class="text-[11px] mb-2" :style="{ color: 'var(--color-text-tertiary)' }">{{ q.email }}</p>
        <div class="flex items-center justify-between gap-3 pt-2 border-t" :style="{ borderColor: 'var(--color-border)' }">
          <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-text)' }">
            {{ fmtMyr(q.estimate_min_myr) }} – {{ fmtMyr(q.estimate_max_myr) }}
          </p>
          <p class="text-[11px]" :style="{ color: 'var(--color-text-secondary)' }">{{ fmtDate(q.submitted_at) }}</p>
        </div>
      </button>
    </div>
  </div>
</template>
