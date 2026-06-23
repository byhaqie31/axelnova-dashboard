<script setup lang="ts">
import ClientFormModal from '~/components/admin/ClientFormModal.vue'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()

interface Client {
  id: number
  name: string
  email: string
  phone: string | null
  company: string | null
  notes: string | null
  tags: string[]
  inquiries_count: number
  quotations_count: number
  orders_count: number
  created_at: string
}

const clients = ref<Client[]>([])
const meta = ref<{ current_page: number; last_page: number; total: number } | null>(null)
const loading = ref(true)
const error = ref('')
const modalOpen = ref(false)

const filters = reactive({ search: '', page: 1 })

async function fetchClients() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams({ paginate: '1', page: String(filters.page) })
    if (filters.search) params.set('search', filters.search)

    const res = await apiFetch<{ data: Client[]; meta: any }>(`/api/v1/admin/clients?${params}`)
    clients.value = res.data
    meta.value = res.meta
  }
  catch {
    error.value = 'Failed to load clients. Check your session.'
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchClients)

let searchTimer: ReturnType<typeof setTimeout>
watch(() => filters.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { filters.page = 1; fetchClients() }, 400)
})
watch(() => filters.page, () => fetchClients())

function onSaved(c: Client) {
  // Jump straight to the new client's detail.
  navigateTo(`/admin/clients/${c.id}`)
}

function fmtDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <!-- Header -->
    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Clients</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">Everyone who's inquired, been quoted, or ordered. Open one to see their full history.</p>
      </div>
      <button type="button" class="btn-pill btn-pill-accent text-[12px] inline-flex items-center gap-1.5" @click="modalOpen = true">
        <UIcon name="i-lucide-plus" class="size-3.5" />
        New client
      </button>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3 mb-6">
      <AdminExpandingSearch v-model="filters.search" placeholder="Search by name, email, company…" />
      <span v-if="meta" class="ml-auto text-[12px]" style="color: var(--color-text-tertiary);">
        <span class="font-semibold" style="color: var(--color-text-secondary);">{{ meta.total }}</span> total
      </span>
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading clients…</div>

    <div v-else-if="!clients.length" class="text-center py-16" style="color: var(--color-text-secondary);">
      No clients found.
    </div>

    <!-- Desktop: table -->
    <div v-else class="hidden md:block admin-table-card">
      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead>
            <tr>
              <th v-for="h in ['Client', 'Company', 'Inquiries', 'Quotations', 'Orders', 'Since']" :key="h"
                class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
                {{ h }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in clients" :key="c.id"
              class="admin-table-row"
              @click="navigateTo(`/admin/clients/${c.id}`)">
              <td class="px-4 py-3.5">
                <p class="text-[13px] font-medium" style="color: var(--color-text);">{{ c.name }}</p>
                <p class="text-[11px]" style="color: var(--color-text-tertiary);">{{ c.email }}</p>
              </td>
              <td class="px-4 py-3.5">
                <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ c.company ?? '—' }}</span>
              </td>
              <td class="px-4 py-3.5 text-[13px] tabular-nums" style="color: var(--color-text-secondary);">{{ c.inquiries_count }}</td>
              <td class="px-4 py-3.5 text-[13px] tabular-nums" style="color: var(--color-text-secondary);">{{ c.quotations_count }}</td>
              <td class="px-4 py-3.5 text-[13px] tabular-nums" style="color: var(--color-text-secondary);">{{ c.orders_count }}</td>
              <td class="px-4 py-3.5 text-[12px]" style="color: var(--color-text-secondary);">{{ fmtDate(c.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Mobile: cards -->
    <div v-if="clients.length" class="md:hidden space-y-2.5">
      <button
        v-for="c in clients"
        :key="c.id"
        type="button"
        class="w-full text-left rounded-xl border p-4 transition-colors hover:bg-(--color-bg-secondary)"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
        @click="navigateTo(`/admin/clients/${c.id}`)"
      >
        <div class="flex items-start justify-between gap-3 mb-1">
          <span class="text-[13px] font-semibold leading-tight" :style="{ color: 'var(--color-text)' }">{{ c.name }}</span>
          <span class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ fmtDate(c.created_at) }}</span>
        </div>
        <p class="text-[11px] mb-3" :style="{ color: 'var(--color-text-tertiary)' }">{{ c.email }}<span v-if="c.company"> · {{ c.company }}</span></p>
        <div class="pt-2 border-t flex items-center gap-4 text-[11px]" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }">
          <span>{{ c.inquiries_count }} inquiries</span>
          <span>{{ c.quotations_count }} quotes</span>
          <span>{{ c.orders_count }} orders</span>
        </div>
      </button>
    </div>

    <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
      <button :disabled="filters.page <= 1" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page--">← Prev</button>
      <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ filters.page }} / {{ meta.last_page }}</span>
      <button :disabled="filters.page >= meta.last_page" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page++">Next →</button>
    </div>

    <ClientFormModal :open="modalOpen" @close="modalOpen = false" @saved="onSaved" />
  </div>
</template>
