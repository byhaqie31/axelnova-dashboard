<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()
const { config, loadConfig } = usePricingEngine()
const toast = useAdminToast()

interface Order {
  id: number
  order_number: string
  quotation_id: number
  client_id: number
  reference_code: string | null
  package_key: string | null
  estimate_eta_value: number | null
  estimate_eta_unit: 'hour' | 'day' | 'week' | 'month' | null
  submitted_at: string | null
  name: string | null
  email: string | null
  phone: string | null
  company: string | null
  value_min_myr: string
  value_max_myr: string
  final_amount_myr: string
  deposit_pct: number | null
  deposit_due_myr: number
  amount_paid_myr: string
  remaining_myr: number
  payment_status: 'unpaid' | 'deposit_paid' | 'paid'
  status: string
  started_at: string | null
  delivered_at: string | null
  completed_at: string | null
  due_at: string | null
  notes: string | null
  created_at: string
  updated_at: string | null
  updated_by: { id: number, name: string } | null
  estimate_min_myr?: string
  estimate_max_myr?: string
  quotation_document?: Record<string, any> | null
  quotation_scope?: Record<string, any> | null
  quotation_addons?: { key: string; label: string; amount_myr: string }[]
  invoices?: OrderInvoice[]
  receipts?: OrderReceipt[]
  payments?: OrderPayment[]
}

interface OrderInvoice {
  id: number
  type: 'deposit' | 'partial' | 'final'
  number: string
  status: 'issued' | 'paid' | 'void'
  amount_total: string
  amount_paid: string | null
  payment_ref: string | null
  payment_method: string | null
  issued_at: string | null
  paid_at: string | null
  pdf_path: string
}

interface OrderReceipt {
  id: number
  number: string
  invoice_id: number | null
  invoice_number: string | null
  status: 'issued' | 'void'
  amount: string
  payment_ref: string | null
  payment_method: string | null
  issued_at: string | null
  pdf_path: string
}

interface OrderPayment {
  id: number
  payment_number: string
  type: 'payment' | 'refund'
  method: string
  status: string
  amount_myr: string
  reference: string | null
  paid_at: string | null
}

const order = ref<Order | null>(null)
const loading = ref(true)
const error = ref('')
const statusLoading = ref(false)

useHead(() => ({
  title: order.value ? `${order.value.order_number} — Order` : 'Order — Admin',
}))

// Mutation endpoints (status/schedule) return a lean order without the
// quotation snapshot — merge so the scope section doesn't blink out until refetch.
function applyOrderUpdate(updated: Order) {
  order.value = order.value ? { ...order.value, ...updated } : updated
}

async function fetchOrder() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: Order }>(`/api/v1/admin/orders/${route.params.id}`)
    order.value = res.data
  }
  catch {
    error.value = 'Failed to load order.'
  }
  finally {
    loading.value = false
  }
}

async function setStatus(next: string) {
  if (!order.value) return
  statusLoading.value = true
  try {
    const res = await apiFetch<{ message: string; order: { data: Order } | Order }>(
      `/api/v1/admin/orders/${order.value.id}/status`,
      { method: 'POST', body: { status: next } },
    )
    applyOrderUpdate(((res.order as any).data ?? res.order) as Order)
    toast.success('Status updated', `Order set to ${statusLabels[next] ?? next}.`)
  }
  catch {
    toast.error('Couldn’t update status', 'Something went wrong. Please try again.')
  }
  finally {
    statusLoading.value = false
  }
}

// ── Expected completion (SLA) ───────────────────────────────────────────────
const dueSaving = ref(false)
const dueDraft = ref('')

watch(order, (o) => { dueDraft.value = o?.due_at ?? '' })

const isOverdue = computed(() => {
  const o = order.value
  if (!o?.due_at || o.status === 'completed' || o.status === 'cancelled') return false
  const due = new Date(o.due_at)
  due.setHours(23, 59, 59, 999)
  return due.getTime() < Date.now()
})

async function saveDue() {
  if (!order.value) return
  dueSaving.value = true
  try {
    const res = await apiFetch<{ message: string; order: { data: Order } | Order }>(
      `/api/v1/admin/orders/${order.value.id}/schedule`,
      { method: 'POST', body: { due_at: dueDraft.value || null } },
    )
    applyOrderUpdate(((res.order as any).data ?? res.order) as Order)
    toast.success('Expected completion updated', 'The order’s target date is set.')
  }
  catch {
    toast.error('Couldn’t update date', 'Something went wrong. Please try again.')
  }
  finally {
    dueSaving.value = false
  }
}

const paymentMeta: Record<Order['payment_status'], { label: string; color: string; bg: string }> = {
  unpaid: { label: 'Unpaid', color: 'var(--color-warning)', bg: 'var(--color-bg-secondary)' },
  deposit_paid: { label: 'Deposit paid', color: 'var(--color-accent)', bg: 'var(--color-accent-soft)' },
  paid: { label: 'Paid in full', color: 'var(--color-success)', bg: 'var(--color-success-soft)' },
}

onMounted(() => {
  fetchOrder()
  loadConfig()
})

function fmtMyr(amount: string | number) {
  const n = Number(amount)
  return n >= 1000 ? `RM ${(n / 1000).toFixed(0)}k` : `RM ${n.toLocaleString()}`
}

// Exact, non-abbreviated — for payment figures where every ringgit matters.
function fmtMyrExact(amount: string | number) {
  return `RM ${Number(amount).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function fmtDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const statusOptions = ['pending', 'in_progress', 'delivered', 'completed', 'cancelled']

const statusLabels: Record<string, string> = {
  pending: 'Pending',
  in_progress: 'In progress',
  delivered: 'Delivered',
  completed: 'Completed',
  cancelled: 'Cancelled',
}

interface TimelineStep {
  key: string
  label: string
  at: string | null
}

const timeline = computed<TimelineStep[]>(() => {
  if (!order.value) return []
  return [
    { key: 'pending', label: 'Order created', at: order.value.created_at },
    { key: 'in_progress', label: 'Work started', at: order.value.started_at },
    { key: 'delivered', label: 'Delivered to client', at: order.value.delivered_at },
    { key: 'completed', label: 'Engagement closed', at: order.value.completed_at },
  ]
})

// Once the order leaves "pending", the quotation is confirmed — the agreed
// total and deposit lock; only payments can still be recorded.
const confirmed = computed(() => !!order.value && order.value.status !== 'pending')

// Package slug → human name from the pricing config (best-effort).
const packageLabel = computed(() => {
  const key = order.value?.package_key
  if (!key || !config.value) return null
  for (const c of config.value.categories) {
    const p = c.packages.find(p => p.key === key)
    if (p) return p.name
  }
  return null
})

const lineItems = computed(() => {
  const items = order.value?.quotation_document?.items
  return Array.isArray(items) ? items : []
})

</script>

<template>
  <div class="max-w-5xl mx-auto px-6 pt-10 pb-32">

    <NuxtLink to="/admin/orders" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All orders
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
    <p v-else-if="error" style="color: var(--color-danger);">{{ error }}</p>

    <div v-else-if="order" class="grid lg:grid-cols-[1fr_300px] gap-8 items-start">

      <div class="space-y-6">

        <div class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <div class="flex items-start justify-between flex-wrap gap-4 mb-5">
            <div>
              <p class="font-mono text-[20px] font-bold mb-1" style="color: var(--color-accent);">{{ order.order_number }}</p>
              <p class="text-[22px] font-bold tracking-tight" style="color: var(--color-text);">{{ order.name ?? '—' }}</p>
              <p v-if="order.company" class="text-[14px] mt-0.5" style="color: var(--color-text-secondary);">{{ order.company }}</p>
            </div>
            <AdminStatusPill :status="order.status" size="md" />
          </div>
          <div class="grid sm:grid-cols-3 gap-4 pt-4 border-t" style="border-color: var(--color-border);">
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Email</p>
              <a v-if="order.email" :href="`mailto:${order.email}`" class="text-[13px] font-medium" style="color: var(--color-accent);">{{ order.email }}</a>
              <span v-else class="text-[13px]" :style="{ color: 'var(--color-text-tertiary)' }">—</span>
            </div>
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Phone</p>
              <a v-if="order.phone" :href="`tel:${order.phone}`" class="text-[13px] font-medium" style="color: var(--color-text);">{{ order.phone }}</a>
              <span v-else class="text-[13px]" :style="{ color: 'var(--color-text-tertiary)' }">—</span>
            </div>
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Total value</p>
              <p class="text-[13px] font-semibold" style="color: var(--color-text);">
                {{ fmtMyrExact(order.final_amount_myr) }}
              </p>
            </div>
          </div>
        </div>

        <!-- Scope snapshot (confirmed quotation, read-only) -->
        <div class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <div class="flex items-center justify-between gap-3 mb-4">
            <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">Scope snapshot</p>
            <span v-if="confirmed" class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full"
              :style="{ color: 'var(--color-text-secondary)', background: 'var(--color-bg-secondary)' }">
              <UIcon name="i-lucide-lock" class="size-3" /> Confirmed
            </span>
          </div>

          <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px]" style="color: var(--color-text-secondary);">
            <span v-if="packageLabel" class="font-medium" style="color: var(--color-text);">{{ packageLabel }}</span>
            <code v-if="order.package_key" class="font-mono text-[12px]" style="color: var(--color-text-tertiary);">{{ order.package_key }}</code>
            <span v-if="order.estimate_eta_value && order.estimate_eta_unit">· {{ formatEta(order.estimate_eta_value, order.estimate_eta_unit) }}</span>
            <span v-if="order.estimate_min_myr && order.estimate_max_myr">· Est. {{ fmtMyr(order.estimate_min_myr) }} – {{ fmtMyr(order.estimate_max_myr) }}</span>
          </div>

          <div v-if="lineItems.length" class="mt-5 pt-4 border-t" style="border-color: var(--color-border);">
            <p class="text-[11px] font-medium uppercase tracking-wider mb-2" style="color: var(--color-text-tertiary);">Line items</p>
            <div class="space-y-2">
              <div v-for="(it, i) in lineItems" :key="i" class="flex justify-between items-baseline gap-4">
                <span class="text-[13px]" style="color: var(--color-text);">{{ it.title }}<span v-if="Number(it.qty) > 1" class="text-[12px]" style="color: var(--color-text-tertiary);"> × {{ it.qty }}</span></span>
                <span class="text-[13px] font-semibold tabular-nums whitespace-nowrap" style="color: var(--color-text);">{{ fmtMyrExact((Number(it.qty) || 0) * (Number(it.rate) || 0)) }}</span>
              </div>
            </div>
          </div>

          <div v-if="order.quotation_addons?.length" class="mt-4 pt-4 border-t" style="border-color: var(--color-border);">
            <p class="text-[11px] font-medium uppercase tracking-wider mb-2" style="color: var(--color-text-tertiary);">Add-ons</p>
            <div class="space-y-2">
              <div v-for="a in order.quotation_addons" :key="a.key" class="flex justify-between items-center gap-4">
                <span class="text-[13px]" style="color: var(--color-text);">{{ a.label }}</span>
                <span class="text-[13px] font-semibold tabular-nums whitespace-nowrap" style="color: var(--color-text);">{{ fmtMyrExact(a.amount_myr) }}</span>
              </div>
            </div>
          </div>

          <AdminScopeDetails :scope="order.quotation_scope" variant="section" />

          <p class="text-[12px] mt-4 pt-4 border-t" style="color: var(--color-text-tertiary); border-color: var(--color-border);">
            Source quotation
            <NuxtLink :to="`/admin/quotations/${order.quotation_id}`" class="underline ml-1" :style="{ color: 'var(--color-accent)' }">{{ order.reference_code ?? `#${order.quotation_id}` }}</NuxtLink>
            <span v-if="order.submitted_at"> · submitted {{ fmtDate(order.submitted_at) }}</span>
            <span v-if="order.updated_by"> · last updated by {{ order.updated_by.name }}</span>
          </p>
        </div>

        <!-- Payment -->
        <div class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <div class="flex items-center justify-between gap-3 mb-5">
            <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">Payment</p>
            <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full"
              :style="{ color: paymentMeta[order.payment_status].color, background: paymentMeta[order.payment_status].bg }">
              {{ paymentMeta[order.payment_status].label }}
            </span>
          </div>

          <!-- Read-only summary (figures are ledger-derived) -->
          <div class="grid grid-cols-2 gap-x-4 gap-y-4">
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Total</p>
              <p class="text-[15px] font-bold tabular-nums" style="color: var(--color-text);">{{ fmtMyrExact(order.final_amount_myr) }}</p>
            </div>
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Deposit ({{ order.deposit_pct ?? 0 }}%)</p>
              <p class="text-[15px] font-semibold tabular-nums" style="color: var(--color-text-secondary);">{{ fmtMyrExact(order.deposit_due_myr) }}</p>
            </div>
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Paid</p>
              <p class="text-[15px] font-semibold tabular-nums" style="color: var(--color-success);">{{ fmtMyrExact(order.amount_paid_myr) }}</p>
            </div>
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Remaining</p>
              <p class="text-[15px] font-bold tabular-nums" :style="{ color: Number(order.remaining_myr) > 0 ? 'var(--color-warning)' : 'var(--color-success)' }">{{ fmtMyrExact(order.remaining_myr) }}</p>
            </div>
          </div>

          <!-- Paid-vs-total progress -->
          <div class="mt-5 h-1.5 rounded-full overflow-hidden" style="background: var(--color-bg-secondary);">
            <div class="h-full rounded-full transition-[width] duration-500"
              :style="{
                width: `${Math.min(100, Number(order.final_amount_myr) > 0 ? (Number(order.amount_paid_myr) / Number(order.final_amount_myr)) * 100 : 0)}%`,
                background: 'var(--color-success)',
              }" />
          </div>

          <!-- Payments (read-only; manage in the Payments module) -->
          <div v-if="order.payments?.length" class="mt-5 pt-5 border-t space-y-2" style="border-color: var(--color-border);">
            <NuxtLink v-for="p in order.payments" :key="p.id" :to="`/admin/payments/${p.id}`"
              class="flex items-center justify-between gap-3 rounded-xl border p-3 transition-colors hover:bg-(--color-bg-secondary)"
              :style="{ borderColor: 'var(--color-border)' }">
              <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-mono text-[12px] font-semibold" style="color: var(--color-text);">{{ p.payment_number }}</span>
                  <AdminStatusPill :status="p.status" />
                  <span v-if="p.type === 'refund'" class="text-[10px] font-semibold uppercase tracking-wider" style="color: var(--color-danger);">Refund</span>
                </div>
                <p class="text-[11px] mt-1 capitalize" style="color: var(--color-text-tertiary);">{{ p.method.replace('_', ' ') }} · {{ fmtDate(p.paid_at) }}</p>
              </div>
              <span class="text-[13px] font-semibold shrink-0" :style="{ color: Number(p.amount_myr) < 0 ? 'var(--color-danger)' : 'var(--color-text)' }">
                {{ Number(p.amount_myr) < 0 ? '−' : '' }}{{ fmtMyrExact(Math.abs(Number(p.amount_myr))) }}
              </span>
            </NuxtLink>
          </div>

          <!-- Shortcuts into the Payments module -->
          <div class="flex flex-wrap gap-2 pt-5 mt-5 border-t" style="border-color: var(--color-border);">
            <NuxtLink :to="`/admin/payments/new?order_id=${order.id}`" class="btn-pill btn-pill-primary text-[12px]" style="height: 34px; padding: 0 16px;">
              Record payment
            </NuxtLink>
            <NuxtLink :to="`/admin/payments?order_id=${order.id}`" class="btn-pill btn-pill-ghost text-[12px]" style="height: 34px; padding: 0 16px;">
              View all payments
            </NuxtLink>
          </div>
        </div>

        <!-- Invoices -->
        <div class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Invoices</p>

          <div v-if="order.invoices?.length" class="space-y-2 mb-5">
            <NuxtLink v-for="d in order.invoices" :key="d.id" :to="`/admin/invoices/${d.id}`"
              class="flex items-center justify-between gap-3 rounded-xl border p-3 transition-colors hover:bg-(--color-bg-secondary)"
              :style="{ borderColor: 'var(--color-border)' }">
              <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-mono text-[13px] font-semibold" style="color: var(--color-text);">{{ d.number }}</span>
                  <span class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded"
                    :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }">{{ d.type }}</span>
                  <AdminStatusPill :status="d.status" />
                </div>
                <p class="text-[11px] mt-1" style="color: var(--color-text-tertiary);">
                  {{ fmtMyrExact(d.amount_total) }}<span v-if="d.amount_paid"> · paid {{ fmtMyrExact(d.amount_paid) }}</span> · {{ fmtDate(d.issued_at) }}
                </p>
              </div>
              <UIcon name="i-lucide-chevron-right" class="size-4 shrink-0" :style="{ color: 'var(--color-text-tertiary)' }" />
            </NuxtLink>
          </div>
          <p v-else class="text-[13px] mb-5" style="color: var(--color-text-tertiary);">No invoices issued yet.</p>

          <!-- Shortcuts into the Invoices module -->
          <div class="flex flex-wrap gap-2 pt-4 border-t" style="border-color: var(--color-border);">
            <NuxtLink :to="`/admin/invoices/new?order_id=${order.id}`" class="btn-pill btn-pill-primary text-[12px]" style="height: 34px; padding: 0 16px;">
              Issue invoice
            </NuxtLink>
            <NuxtLink :to="`/admin/invoices?order_id=${order.id}`" class="btn-pill btn-pill-ghost text-[12px]" style="height: 34px; padding: 0 16px;">
              View all invoices
            </NuxtLink>
          </div>
        </div>

        <!-- Receipts -->
        <div class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Receipts</p>

          <div v-if="order.receipts?.length" class="space-y-2 mb-5">
            <div v-for="r in order.receipts" :key="r.id"
              class="flex items-center justify-between gap-3 rounded-xl border p-3"
              :style="{ borderColor: 'var(--color-border)' }">
              <div class="min-w-0">
                <span class="font-mono text-[13px] font-semibold" style="color: var(--color-text);">{{ r.number }}</span>
                <p class="text-[11px] mt-1" style="color: var(--color-text-tertiary);">
                  {{ fmtMyrExact(r.amount) }}<span v-if="r.invoice_number"> · for {{ r.invoice_number }}</span> · {{ fmtDate(r.issued_at) }}
                </p>
              </div>
              <a :href="r.pdf_path" target="_blank" rel="noopener"
                class="btn-pill btn-pill-ghost text-[12px] shrink-0" style="height: 32px; padding: 0 16px;">
                View PDF
              </a>
            </div>
          </div>
          <p v-else class="text-[13px]" style="color: var(--color-text-tertiary);">No receipts issued yet.</p>

          <p class="text-[11px] pt-4 mt-4 border-t" style="color: var(--color-text-tertiary); border-color: var(--color-border);">
            Receipts are issued from a payment — open one in
            <NuxtLink :to="`/admin/payments?order_id=${order.id}`" class="underline" :style="{ color: 'var(--color-accent)' }">Payments</NuxtLink>
            and choose “Issue receipt”.
          </p>
        </div>

      </div>

      <div class="lg:sticky lg:top-20 space-y-4">

        <div class="rounded-2xl border p-5"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Order status</p>
          <div class="flex flex-wrap gap-2">
            <button v-for="s in statusOptions" :key="s" type="button"
              class="status-pill status-pill-button"
              :class="{ 'opacity-50': statusLoading }"
              :data-status="order.status === s ? s : ''"
              :data-active="order.status === s"
              :disabled="statusLoading || order.status === s"
              @click="setStatus(s)">
              {{ statusLabels[s] }}
            </button>
          </div>
        </div>

        <div class="rounded-2xl border p-5"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Timeline</p>
          <ol class="space-y-4">
            <li v-for="step in timeline" :key="step.key" class="flex items-start gap-3">
              <div
                class="size-7 rounded-full inline-flex items-center justify-center shrink-0 mt-0.5"
                :style="step.at ? {
                  background: `var(--status-${step.key}-bg)`,
                  color: `var(--status-${step.key}-fg)`,
                } : {
                  background: 'var(--color-bg-secondary)',
                  color: 'var(--color-text-tertiary)',
                }"
              >
                <UIcon :name="step.at ? 'i-lucide-check' : 'i-lucide-circle'" class="size-3.5" />
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-[13px] font-medium" :style="{ color: step.at ? 'var(--color-text)' : 'var(--color-text-secondary)' }">{{ step.label }}</p>
                <p class="text-[11px] mt-0.5" :style="{ color: 'var(--color-text-tertiary)' }">{{ fmtDate(step.at) }}</p>
              </div>
            </li>
          </ol>
        </div>

        <div class="rounded-2xl border p-5"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <div class="flex items-center justify-between gap-2 mb-3">
            <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">Expected completion</p>
            <span v-if="isOverdue" class="text-[10px] font-semibold px-2 py-0.5 rounded-full"
              :style="{ color: 'var(--color-danger)', background: 'var(--color-danger-soft, var(--color-bg-secondary))' }">Overdue</span>
          </div>
          <input v-model="dueDraft" type="date" class="doc-input">
          <p class="text-[11px] mt-2" style="color: var(--color-text-tertiary);">Target delivery date — your SLA for this order.</p>
          <button type="button" class="btn-pill btn-pill-primary w-full justify-center text-[13px] mt-3"
            :class="{ 'opacity-50': dueSaving || dueDraft === (order.due_at ?? '') }"
            :disabled="dueSaving || dueDraft === (order.due_at ?? '')" @click="saveDue">
            {{ dueSaving ? 'Saving…' : 'Save date' }}
          </button>
        </div>

        <div class="rounded-2xl border p-5 space-y-3"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Contact</p>

          <a v-if="order.email" :href="`mailto:${order.email}?subject=Re: ${order.order_number}`"
            class="btn-pill btn-pill-ghost w-full justify-center text-[13px]">
            Email client
          </a>

          <a v-if="order.phone" :href="`https://wa.me/${order.phone.replace(/\\D/g, '')}?text=Hi%20${encodeURIComponent(order.name ?? '')}%2C%20update%20on%20${order.order_number}.`"
            target="_blank" rel="noopener"
            class="btn-pill btn-pill-ghost w-full justify-center text-[13px]">
            WhatsApp
          </a>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.doc-input {
  display: block;
  width: 100%;
  height: 38px;
  padding: 0 12px;
  border-radius: 10px;
  font-size: 13px;
  background: var(--color-bg-secondary);
  border: 1px solid var(--color-border);
  color: var(--color-text);
  transition: border-color 0.18s ease;
}
.doc-input:focus {
  outline: none;
  border-color: var(--color-accent);
}
.doc-input:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
</style>
