<script setup lang="ts">
import ClientFormModal from '~/components/admin/ClientFormModal.vue'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()

interface InquiryRow { id: number; status: string; project_type: string | null; quotation_id: number | null; created_at: string }
interface QuotationRow { id: number; reference_code: string; status: string; estimate_max_myr: string | number | null; submitted_at: string | null }
interface OrderRow { id: number; order_number: string; status: string; payment_status: string | null; final_amount_myr: string | number | null; created_at: string }

interface Client {
  id: number
  name: string
  email: string
  phone: string | null
  company: string | null
  notes: string | null
  tags: string[]
  created_at: string
  inquiries_count: number
  quotations_count: number
  orders_count: number
  inquiries: InquiryRow[]
  quotations: QuotationRow[]
  orders: OrderRow[]
}

const client = ref<Client | null>(null)
const loading = ref(true)
const error = ref('')
const modalOpen = ref(false)

useHead(() => ({ title: client.value ? `${client.value.name} — Client` : 'Client — Admin' }))

async function fetchClient() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: Client }>(`/api/v1/admin/clients/${route.params.id}`)
    client.value = res.data
  }
  catch {
    error.value = 'Failed to load client.'
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchClient)

function onSaved(c: Partial<Client>) {
  if (client.value) Object.assign(client.value, c)
}

function fmtDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}
function fmtRm(n: string | number | null) {
  return `RM ${(Number(n) || 0).toLocaleString()}`
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <NuxtLink
to="/admin/clients" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All clients
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
    <p v-else-if="error" style="color: var(--color-danger);">{{ error }}</p>

    <div v-else-if="client" class="grid lg:grid-cols-[1fr_300px] gap-8 items-start">

      <div class="space-y-6">

        <!-- Header -->
        <div class="rounded-2xl border p-6" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <div class="flex items-start justify-between flex-wrap gap-4 mb-5">
            <div>
              <p class="text-[22px] font-bold tracking-tight" style="color: var(--color-text);">{{ client.name }}</p>
              <div v-if="client.tags.length" class="flex flex-wrap gap-1.5 mt-2.5">
                <span
v-for="t in client.tags" :key="t" class="text-[11px] px-2 py-0.5 rounded-full"
                  :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }">{{ t }}</span>
              </div>
            </div>
            <button type="button" class="btn-pill btn-pill-ghost text-[12px] gap-1.5" @click="modalOpen = true">
              <UIcon name="i-lucide-pencil" class="size-3.5" /> Edit
            </button>
          </div>
          <div class="grid sm:grid-cols-3 gap-4 pt-4 border-t" style="border-color: var(--color-border);">
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Email</p>
              <a :href="`mailto:${client.email}`" class="text-[13px] font-medium break-all" style="color: var(--color-accent);">{{ client.email }}</a>
            </div>
            <div v-if="client.phone">
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Phone</p>
              <a :href="`tel:${client.phone}`" class="text-[13px] font-medium" style="color: var(--color-text);">{{ client.phone }}</a>
            </div>
            <div v-if="client.company">
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Company</p>
              <p class="text-[13px] font-medium" style="color: var(--color-text);">{{ client.company }}</p>
            </div>
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Client since</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ fmtDate(client.created_at) }}</p>
            </div>
          </div>
          <div v-if="client.notes" class="mt-4 pt-4 border-t" style="border-color: var(--color-border);">
            <p class="text-[11px] font-medium uppercase tracking-wider mb-1.5" style="color: var(--color-text-tertiary);">Notes</p>
            <p class="text-[13px] leading-relaxed whitespace-pre-line" style="color: var(--color-text-secondary);">{{ client.notes }}</p>
          </div>
        </div>

        <!-- Inquiries -->
        <div class="rounded-2xl border p-6" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Inquiries ({{ client.inquiries_count }})</p>
          <p v-if="!client.inquiries.length" class="text-[13px]" style="color: var(--color-text-tertiary);">No inquiries.</p>
          <div v-else class="space-y-1.5">
            <NuxtLink
v-for="i in client.inquiries" :key="i.id" :to="`/admin/inquiries/${i.id}`"
              class="flex items-center justify-between gap-3 rounded-xl border px-4 py-3 transition-colors hover:bg-(--color-bg-secondary)"
              :style="{ borderColor: 'var(--color-border)' }">
              <div class="min-w-0">
                <p class="text-[13px] font-medium truncate" style="color: var(--color-text);">{{ i.project_type ?? 'Inquiry' }}</p>
                <p class="text-[11px]" style="color: var(--color-text-tertiary);">{{ fmtDate(i.created_at) }}</p>
              </div>
              <AdminStatusPill :status="i.status" />
            </NuxtLink>
          </div>
        </div>

        <!-- Quotations -->
        <div class="rounded-2xl border p-6" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Quotations ({{ client.quotations_count }})</p>
          <p v-if="!client.quotations.length" class="text-[13px]" style="color: var(--color-text-tertiary);">No quotations.</p>
          <div v-else class="space-y-1.5">
            <NuxtLink
v-for="q in client.quotations" :key="q.id" :to="`/admin/quotations/${q.id}`"
              class="flex items-center justify-between gap-3 rounded-xl border px-4 py-3 transition-colors hover:bg-(--color-bg-secondary)"
              :style="{ borderColor: 'var(--color-border)' }">
              <div class="min-w-0">
                <p class="text-[13px] font-mono font-medium truncate" style="color: var(--color-text);">{{ q.reference_code }}</p>
                <p class="text-[11px]" style="color: var(--color-text-tertiary);">{{ fmtDate(q.submitted_at) }}</p>
              </div>
              <div class="flex items-center gap-3 shrink-0">
                <span class="text-[12px] font-medium tabular-nums" style="color: var(--color-text-secondary);">{{ fmtRm(q.estimate_max_myr) }}</span>
                <AdminStatusPill :status="q.status" />
              </div>
            </NuxtLink>
          </div>
        </div>

        <!-- Orders -->
        <div class="rounded-2xl border p-6" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Orders ({{ client.orders_count }})</p>
          <p v-if="!client.orders.length" class="text-[13px]" style="color: var(--color-text-tertiary);">No orders.</p>
          <div v-else class="space-y-1.5">
            <NuxtLink
v-for="o in client.orders" :key="o.id" :to="`/admin/orders/${o.id}`"
              class="flex items-center justify-between gap-3 rounded-xl border px-4 py-3 transition-colors hover:bg-(--color-bg-secondary)"
              :style="{ borderColor: 'var(--color-border)' }">
              <div class="min-w-0">
                <p class="text-[13px] font-mono font-medium truncate" style="color: var(--color-text);">{{ o.order_number }}</p>
                <p class="text-[11px]" style="color: var(--color-text-tertiary);">{{ fmtDate(o.created_at) }}</p>
              </div>
              <div class="flex items-center gap-3 shrink-0">
                <span class="text-[12px] font-medium tabular-nums" style="color: var(--color-text-secondary);">{{ fmtRm(o.final_amount_myr) }}</span>
                <AdminStatusPill :status="o.status" />
              </div>
            </NuxtLink>
          </div>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="lg:sticky lg:top-20 space-y-4">
        <div class="rounded-2xl border p-5" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Activity</p>
          <div class="space-y-2.5">
            <div class="flex items-center justify-between">
              <span class="text-[13px]" style="color: var(--color-text-secondary);">Inquiries</span>
              <span class="text-[15px] font-bold tabular-nums" style="color: var(--color-text);">{{ client.inquiries_count }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-[13px]" style="color: var(--color-text-secondary);">Quotations</span>
              <span class="text-[15px] font-bold tabular-nums" style="color: var(--color-text);">{{ client.quotations_count }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-[13px]" style="color: var(--color-text-secondary);">Orders</span>
              <span class="text-[15px] font-bold tabular-nums" style="color: var(--color-text);">{{ client.orders_count }}</span>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border p-5 space-y-3" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Actions</p>
          <a :href="`mailto:${client.email}`" class="btn-pill btn-pill-ghost w-full justify-center text-[13px]">Email client</a>
          <a
v-if="client.phone"
            :href="`https://wa.me/${client.phone.replace(/\D/g, '')}`"
            target="_blank" rel="noopener"
            class="btn-pill btn-pill-success w-full justify-center text-[13px]">WhatsApp</a>
        </div>
      </div>
    </div>

    <ClientFormModal :open="modalOpen" :client="client" @close="modalOpen = false" @saved="onSaved" />
  </div>
</template>
