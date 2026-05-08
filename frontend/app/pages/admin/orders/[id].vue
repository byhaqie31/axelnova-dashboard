<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()

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
  status: string
  started_at: string | null
  delivered_at: string | null
  completed_at: string | null
  notes: string | null
  created_at: string
}

const order = ref<Order | null>(null)
const loading = ref(true)
const error = ref('')
const statusLoading = ref(false)
const actionMessage = ref('')

useHead(() => ({
  title: order.value ? `${order.value.order_number} — Order` : 'Order — Admin',
}))

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
    const updated = (res.order as any).data ?? res.order
    order.value = updated as Order
    actionMessage.value = `Order status set to ${statusLabels[next] ?? next}.`
  }
  catch {
    actionMessage.value = 'Failed to update status.'
  }
  finally {
    statusLoading.value = false
  }
}

onMounted(fetchOrder)

function fmtMyr(amount: string | number) {
  const n = Number(amount)
  return n >= 1000 ? `RM ${(n / 1000).toFixed(0)}k` : `RM ${n.toLocaleString()}`
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
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Order value</p>
              <p class="text-[13px] font-semibold" style="color: var(--color-text);">
                {{ fmtMyr(order.value_min_myr) }} – {{ fmtMyr(order.value_max_myr) }}
              </p>
            </div>
          </div>
        </div>

        <!-- Timeline -->
        <div class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-5" style="color: var(--color-text-tertiary);">Timeline</p>
          <ol class="space-y-5">
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

        <!-- Source quotation -->
        <div class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Scope snapshot</p>
          <p class="text-[13px]" style="color: var(--color-text-secondary);">
            <span v-if="order.estimate_eta_value && order.estimate_eta_unit">{{ formatEta(order.estimate_eta_value, order.estimate_eta_unit) }} · </span>
            Package: <code class="font-mono" style="color: var(--color-text);">{{ order.package_key ?? '—' }}</code>
          </p>
          <p class="text-[12px] mt-2" style="color: var(--color-text-tertiary);">
            Source quotation
            <NuxtLink :to="`/admin/quotations/${order.quotation_id}`" class="underline ml-1" :style="{ color: 'var(--color-accent)' }">{{ order.reference_code ?? `#${order.quotation_id}` }}</NuxtLink>
            <span v-if="order.submitted_at"> · submitted {{ fmtDate(order.submitted_at) }}</span>
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

        <p v-if="actionMessage" class="text-[12px] text-center px-3" style="color: var(--color-text-secondary);">
          {{ actionMessage }}
        </p>
      </div>
    </div>
  </div>
</template>
