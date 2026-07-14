<script setup lang="ts">
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
  deposit_due_myr: number
  amount_paid_myr: string
  remaining_myr: number
}

const orderId = computed(() => route.query.order_id ? String(route.query.order_id) : '')
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
  catch {
    toast.error('Couldn’t issue invoice', 'Please try again.')
  }
  finally {
    issuing.value = false
  }
}

onMounted(fetchOrder)
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <NuxtLink
      :to="orderId ? `/admin/orders/${orderId}` : '/admin/invoices'"
      class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70" :style="{ color: 'var(--color-text-secondary)' }">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> {{ orderId ? 'Back to order' : 'All invoices' }}
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
    <p v-else-if="error" class="text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <template v-else-if="order">
      <h1 class="text-[24px] font-bold tracking-tight mb-1" style="color: var(--color-text);">Issue invoice</h1>
      <p class="text-[14px] mb-6" style="color: var(--color-text-secondary);">
        For order <span class="font-mono" style="color: var(--color-accent);">{{ order.order_number }}</span> · {{ order.name ?? '—' }}
      </p>

      <AdminInvoiceForm :order="order" mode="create" :submitting="issuing" @submit="issueInvoice" />
    </template>
  </div>
</template>
