<script setup lang="ts">
import type { OrderPlanView } from '~/composables/paymentPlan'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

interface Order {
  id: number
  order_number: string
  name: string | null
  final_amount_myr: string
  deposit_pct: number | null
  deposit_amount_myr?: number | string | null
  deposit_due_myr: number
  amount_paid_myr: string
  remaining_myr: number
  payment_plan?: OrderPlanView | null
  invoices?: { id: number; type: string; status: string; instalment_no?: number | null; care_period?: string | null }[]
  care_plans?: { kind: 'included' | 'separate'; monthly_myr: number | null; starts_on: string; ends_on: string | null }[]
}

const orderId = computed(() => route.query.order_id ? String(route.query.order_id) : '')
// ?instalment=N / ?phase=N / ?care=YYYY-MM (from the order's plan card) open the
// form on that instalment, phase, or Care Plan month.
const initial = computed(() => route.query.instalment
  ? { invoiceType: 'instalment', instalmentNo: Number(route.query.instalment) }
  : route.query.phase
    ? { invoiceType: 'phase', instalmentNo: Number(route.query.phase) }
    : route.query.care
      // care=next lets the form pick the first month not billed yet.
      ? { invoiceType: 'care_plan', ...(route.query.care !== 'next' ? { carePeriod: String(route.query.care) } : {}) }
      : null)
const order = ref<Order | null>(null)
const loading = ref(true)
const error = ref('')
const issuing = ref(false)

async function fetchOrder() {
  if (!orderId.value) { error.value = 'No order specified.'; loading.value = false; return }
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: Order }>(`/api/v1/admin/orders/${orderId.value}`)
    order.value = res.data
  }
  catch {
    error.value = 'Failed to load the order.'
  }
  finally {
    loading.value = false
  }
}

async function issueInvoice(body: Record<string, unknown>) {
  if (!order.value) return
  issuing.value = true
  try {
    const res = await apiFetch<{ document: { id: number } }>(`/api/v1/admin/orders/${order.value.id}/documents`, { method: 'POST', body: { type: 'invoice', ...body } })
    toast.success('Invoice issued', 'Record payments against it from the Payments module.')
    await navigateTo(`/admin/invoices/${res.document.id}`)
  }
  catch (e: any) {
    toast.error('Couldn’t issue invoice', apiError(e, 'Please try again.'))
  }
  finally {
    issuing.value = false
  }
}

// The API's own words for a refusal (e.g. "Phase 1 was settled by payments made
// before the revision") — the first validation error, else the message.
function apiError(e: any, fallback: string): string {
  const errors = e?.data?.errors as Record<string, string[]> | undefined
  return (errors && Object.values(errors)[0]?.[0]) || e?.data?.message || fallback
}

onMounted(fetchOrder)
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-6 md:pt-10 pb-24 md:pb-32">
    <NuxtLink
      :to="orderId ? `/admin/orders/${orderId}` : '/admin/invoices'"
      class="inline-flex items-center gap-2 text-[13px] mb-8 max-md:mb-5 max-md:py-1.5 transition-opacity hover:opacity-70 max-md:active:opacity-60" :style="{ color: 'var(--color-text-secondary)' }">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> {{ orderId ? 'Back to order' : 'All invoices' }}
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
    <p v-else-if="error" class="text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <template v-else-if="order">
      <h1 class="text-[24px] font-bold tracking-tight mb-1" style="color: var(--color-text);">Issue invoice</h1>
      <p class="text-[14px] mb-6" style="color: var(--color-text-secondary);">
        For order <span class="font-mono" style="color: var(--color-accent);">{{ order.order_number }}</span> · {{ order.name ?? '—' }}
      </p>

      <AdminInvoiceForm :order="order" mode="create" :initial="initial" :submitting="issuing" @submit="issueInvoice" />
    </template>
  </div>
</template>
