<script setup lang="ts">
import CustomerFormModal from '~/components/admin/CustomerFormModal.vue'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()

interface InquiryRow { id: number; status: string; project_type: string | null; quotation_id: number | null; created_at: string }
interface QuotationRow { id: number; reference_code: string; status: string; estimate_max_myr: string | number | null; submitted_at: string | null }
interface OrderRow { id: number; order_number: string; status: string; payment_status: string | null; final_amount_myr: string | number | null; created_at: string }

interface Customer {
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

const customer = ref<Customer | null>(null)
const loading = ref(true)
const error = ref('')
const modalOpen = ref(false)

useHead(() => ({ title: customer.value ? `${customer.value.name} — Customer` : 'Customer — Admin' }))

async function fetchCustomer() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: Customer }>(`/api/v1/admin/clients/${route.params.id}`)
    customer.value = res.data
  }
  catch {
    error.value = 'Failed to load customer.'
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchCustomer)

function onSaved(c: Partial<Customer>) {
  if (customer.value) Object.assign(customer.value, c)
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
  <div class="max-w-5xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <NuxtLink to="/admin/customers" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All customers
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
    <p v-else-if="error" style="color: var(--color-danger);">{{ error }}</p>

    <div v-else-if="customer" class="grid lg:grid-cols-[1fr_300px] gap-8 items-start">

      <div class="space-y-6">

        <!-- Header -->
        <div class="rounded-2xl border p-6" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <div class="flex items-start justify-between flex-wrap gap-4 mb-5">
            <div>
              <p class="text-[22px] font-bold tracking-tight" style="color: var(--color-text);">{{ customer.name }}</p>
              <p v-if="customer.company" class="text-[14px] mt-0.5" style="color: var(--color-text-secondary);">{{ customer.company }}</p>
              <div v-if="customer.tags.length" class="flex flex-wrap gap-1.5 mt-2.5">
                <span v-for="t in customer.tags" :key="t" class="text-[11px] px-2 py-0.5 rounded-full"
                  :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }">{{ t }}</span>
              </div>
            </div>
            <button type="button" class="btn-pill btn-pill-ghost text-[12px]" @click="modalOpen = true">
              <UIcon name="i-lucide-pencil" class="size-3.5" /> Edit
            </button>
          </div>
          <div class="grid sm:grid-cols-3 gap-4 pt-4 border-t" style="border-color: var(--color-border);">
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Email</p>
              <a :href="`mailto:${customer.email}`" class="text-[13px] font-medium break-all" style="color: var(--color-accent);">{{ customer.email }}</a>
            </div>
            <div v-if="customer.phone">
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Phone</p>
              <a :href="`tel:${customer.phone}`" class="text-[13px] font-medium" style="color: var(--color-text);">{{ customer.phone }}</a>
            </div>
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Customer since</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ fmtDate(customer.created_at) }}</p>
            </div>
          </div>
          <div v-if="customer.notes" class="mt-4 pt-4 border-t" style="border-color: var(--color-border);">
            <p class="text-[11px] font-medium uppercase tracking-wider mb-1.5" style="color: var(--color-text-tertiary);">Notes</p>
            <p class="text-[13px] leading-relaxed whitespace-pre-line" style="color: var(--color-text-secondary);">{{ customer.notes }}</p>
          </div>
        </div>

        <!-- Inquiries -->
        <div class="rounded-2xl border p-6" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Inquiries ({{ customer.inquiries_count }})</p>
          <p v-if="!customer.inquiries.length" class="text-[13px]" style="color: var(--color-text-tertiary);">No inquiries.</p>
          <div v-else class="space-y-1.5">
            <NuxtLink v-for="i in customer.inquiries" :key="i.id" :to="`/admin/inquiries/${i.id}`"
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
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Quotations ({{ customer.quotations_count }})</p>
          <p v-if="!customer.quotations.length" class="text-[13px]" style="color: var(--color-text-tertiary);">No quotations.</p>
          <div v-else class="space-y-1.5">
            <NuxtLink v-for="q in customer.quotations" :key="q.id" :to="`/admin/quotations/${q.id}`"
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
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Orders ({{ customer.orders_count }})</p>
          <p v-if="!customer.orders.length" class="text-[13px]" style="color: var(--color-text-tertiary);">No orders.</p>
          <div v-else class="space-y-1.5">
            <NuxtLink v-for="o in customer.orders" :key="o.id" :to="`/admin/orders/${o.id}`"
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
              <span class="text-[15px] font-bold tabular-nums" style="color: var(--color-text);">{{ customer.inquiries_count }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-[13px]" style="color: var(--color-text-secondary);">Quotations</span>
              <span class="text-[15px] font-bold tabular-nums" style="color: var(--color-text);">{{ customer.quotations_count }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-[13px]" style="color: var(--color-text-secondary);">Orders</span>
              <span class="text-[15px] font-bold tabular-nums" style="color: var(--color-text);">{{ customer.orders_count }}</span>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border p-5 space-y-3" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Actions</p>
          <a :href="`mailto:${customer.email}`" class="btn-pill btn-pill-ghost w-full justify-center text-[13px]">Email customer</a>
          <a v-if="customer.phone"
            :href="`https://wa.me/${customer.phone.replace(/\D/g, '')}`"
            target="_blank" rel="noopener"
            class="btn-pill btn-pill-ghost w-full justify-center text-[13px]">WhatsApp</a>
        </div>
      </div>
    </div>

    <CustomerFormModal :open="modalOpen" :client="customer" @close="modalOpen = false" @saved="onSaved" />
  </div>
</template>
