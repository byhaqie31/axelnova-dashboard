<script setup lang="ts">
import { formatMyr } from '~/utils/money'
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

// One month of the Revenue overview, broken down: the orders won (sales
// closed), the payments that landed, and who they came from. Same rules as the
// overview row it was opened from, so the totals always match.
const route = useRoute()
const { apiFetch } = useAdminAuth()

interface Summary {
  orders: number
  booked: number
  collected: number
  fees: number
  net: number
  refunded: number
  payments: number
  outstanding: number
}
interface OrderRow {
  id: number
  order_number: string
  client: { id: number; name: string; company: string | null } | null
  label: string
  status: string
  payment_status: string
  value: number
  paid: number
  balance: number
  created_at: string
}
interface PaymentRow {
  id: number
  payment_number: string
  paid_at: string
  client: { id: number; name: string } | null
  order_id: number
  order_number: string | null
  order_month: string | null
  type: string
  method: string
  amount: number
  fee: number
}
interface ClientRow { id: number; name: string; booked: number; collected: number; orders: number; payments: number }
interface MonthDetail {
  month: string
  label: string
  prev: string
  next: string | null
  summary: Summary
  orders: OrderRow[]
  payments: PaymentRow[]
  clients: ClientRow[]
}

const detail = ref<MonthDetail | null>(null)
const loading = ref(true)
const error = ref('')

useHead(() => ({ title: detail.value ? `${detail.value.label} — Revenue` : 'Revenue — Admin' }))

async function fetchMonth() {
  loading.value = true
  error.value = ''
  try {
    detail.value = await apiFetch<MonthDetail>(`/api/v1/admin/revenue/monthly/${route.params.month}`)
  }
  catch (e) {
    const status = (e as { status?: number }).status
    error.value = status === 404 ? 'That month isn’t available.' : 'Failed to load this month. Check your session.'
    detail.value = null
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchMonth)
// Prev/next reuse this page component, so follow the param.
watch(() => route.params.month, fetchMonth)

function fmtDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short' })
}

const methodLabels: Record<string, string> = {
  bank_transfer: 'Bank transfer',
  fpx: 'FPX',
  duitnow: 'DuitNow',
  card: 'Card',
  cash: 'Cash',
  ewallet: 'E-wallet',
  other: 'Other',
}

/** "Jun" for a payment settling an order won in an earlier month. */
function earlierOrderMonth(p: PaymentRow): string | null {
  if (!p.order_month || !detail.value || p.order_month === detail.value.month) return null
  return new Date(`${p.order_month}-01T00:00:00`).toLocaleDateString('en-MY', { month: 'short', year: 'numeric' })
}

const isEmpty = computed(() => !!detail.value && detail.value.summary.orders === 0 && detail.value.summary.payments === 0)

const tiles = computed(() => {
  const s = detail.value?.summary
  if (!s) return []
  return [
    { key: 'orders', label: 'Sales closed', value: String(s.orders), hint: s.orders === 1 ? 'Order won this month' : 'Orders won this month' },
    { key: 'booked', label: 'Booked', value: formatMyr(s.booked), hint: 'Contracted value of those orders', swatch: 'var(--chart-secondary)' },
    { key: 'collected', label: 'Collected', value: formatMyr(s.collected), hint: s.fees > 0 ? `Net of fees ${formatMyr(s.net)}` : 'Cash received, net of refunds', swatch: 'var(--chart-primary)' },
    { key: 'outstanding', label: 'Outstanding', value: formatMyr(s.outstanding), hint: 'Still owed on this month’s orders' },
  ]
})

const cardStyle = { borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-6 md:pt-10 pb-24 md:pb-32">
    <NuxtLink
      to="/admin/revenue" class="inline-flex items-center gap-2 text-[13px] mb-8 max-md:mb-5 transition-opacity hover:opacity-70 active:opacity-60"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> Revenue
    </NuxtLink>

    <div v-if="loading && !detail" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
    <p v-else-if="error" style="color: var(--color-danger);">{{ error }}</p>

    <template v-else-if="detail">
      <!-- Header + month stepper -->
      <div class="flex items-end justify-between gap-4 flex-wrap mb-6 md:mb-8">
        <div>
          <h1 class="text-[24px] md:text-[28px] font-bold tracking-tight" style="color: var(--color-text);">{{ detail.label }}</h1>
          <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">Sales closed, cash received, and who it came from.</p>
        </div>
        <div class="flex gap-1.5 max-md:w-full max-md:gap-2 max-md:*:flex-1">
          <NuxtLink
            :to="`/admin/revenue/${detail.prev}`" class="btn-pill btn-pill-ghost text-[12px] gap-1"
            :aria-label="`Previous month`">
            <UIcon name="i-lucide-chevron-left" class="size-3.5" /> Prev
          </NuxtLink>
          <NuxtLink
            v-if="detail.next" :to="`/admin/revenue/${detail.next}`" class="btn-pill btn-pill-ghost text-[12px] gap-1"
            :aria-label="`Next month`">
            Next <UIcon name="i-lucide-chevron-right" class="size-3.5" />
          </NuxtLink>
        </div>
      </div>

      <!-- Summary -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 max-md:gap-3 mb-4" :class="{ 'opacity-60': loading }">
        <section v-for="t in tiles" :key="t.key" class="rounded-2xl border p-5 max-md:p-4 max-md:min-w-0" :style="cardStyle">
          <div class="flex items-center gap-1.5 mb-1">
            <span v-if="t.swatch" class="size-2 rounded-[2px] shrink-0" :style="{ background: t.swatch }" />
            <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">{{ t.label }}</p>
          </div>
          <p class="text-[24px] max-sm:text-[17px] font-bold tracking-tight tabular-nums leading-none max-md:wrap-anywhere" style="color: var(--color-text);">{{ t.value }}</p>
          <p class="text-[11px] mt-2" style="color: var(--color-text-secondary);">{{ t.hint }}</p>
        </section>
      </div>
      <p v-if="detail.summary.refunded > 0" class="text-[12px] mb-4" style="color: var(--color-danger);">
        {{ formatMyr(detail.summary.refunded) }} refunded this month — already netted off Collected.
      </p>

      <!-- Empty month -->
      <section v-if="isEmpty" class="rounded-2xl border py-16 flex flex-col items-center text-center gap-1" :style="cardStyle">
        <UIcon name="i-lucide-calendar-x" class="size-6 mb-1" :style="{ color: 'var(--color-text-tertiary)' }" />
        <p class="text-[13px] font-medium" :style="{ color: 'var(--color-text)' }">No sales or payments in {{ detail.label }}</p>
        <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">Use Prev / Next to step through other months.</p>
      </section>

      <div v-else class="space-y-6" :class="{ 'opacity-60': loading }">
        <!-- Orders booked -->
        <section>
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">
            Orders booked ({{ detail.orders.length }})
          </p>
          <p v-if="!detail.orders.length" class="text-[13px] rounded-2xl border px-5 py-4" :style="{ ...cardStyle, color: 'var(--color-text-tertiary)' }">
            No new orders won this month — the cash below settles earlier orders.
          </p>
          <template v-else>
            <div class="hidden md:block admin-table-card">
              <div class="overflow-x-auto">
                <table class="w-full text-left">
                  <thead>
                    <tr>
                      <th
                        v-for="h in ['Order', 'Client', 'Status', 'Value', 'Paid to date', 'Balance']" :key="h"
                        class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider"
                        :class="{ 'text-right': ['Value', 'Paid to date', 'Balance'].includes(h) }"
                        style="color: var(--color-text-tertiary);">{{ h }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="o in detail.orders" :key="o.id" class="admin-table-row" @click="navigateTo(`/admin/orders/${o.id}`)">
                      <td class="px-4 py-3.5">
                        <p class="text-[13px] font-mono font-medium" style="color: var(--color-text);">{{ o.order_number }}</p>
                        <p class="text-[11px]" style="color: var(--color-text-tertiary);">{{ o.label }} · {{ fmtDate(o.created_at) }}</p>
                      </td>
                      <td class="px-4 py-3.5 text-[13px]" style="color: var(--color-text-secondary);">{{ o.client?.name ?? '—' }}</td>
                      <td class="px-4 py-3.5"><AdminStatusPill :status="o.status" /></td>
                      <td class="px-4 py-3.5 text-right text-[13px] tabular-nums" style="color: var(--color-text);">{{ formatMyr(o.value) }}</td>
                      <td class="px-4 py-3.5 text-right text-[13px] tabular-nums" style="color: var(--color-text-secondary);">{{ formatMyr(o.paid) }}</td>
                      <td
                        class="px-4 py-3.5 text-right text-[13px] tabular-nums"
                        :style="{ color: o.balance > 0 ? 'var(--color-text)' : 'var(--color-success)' }">
                        {{ o.balance > 0 ? formatMyr(o.balance) : 'Paid' }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="md:hidden space-y-2.5">
              <NuxtLink
                v-for="o in detail.orders" :key="o.id" :to="`/admin/orders/${o.id}`"
                class="block rounded-xl border p-4 transition-opacity active:opacity-70" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
                <div class="flex items-start justify-between gap-3 mb-1">
                  <span class="text-[13px] font-mono font-semibold min-w-0 break-all" style="color: var(--color-text);">{{ o.order_number }}</span>
                  <AdminStatusPill :status="o.status" />
                </div>
                <p class="text-[12px] mb-3" style="color: var(--color-text-secondary);">{{ o.client?.name ?? '—' }} · {{ o.label }}</p>
                <div class="pt-2 border-t flex items-center justify-between gap-3 text-[12px] tabular-nums" :style="{ borderColor: 'var(--color-border)' }">
                  <span style="color: var(--color-text);">{{ formatMyr(o.value) }}</span>
                  <span :style="{ color: o.balance > 0 ? 'var(--color-text-secondary)' : 'var(--color-success)' }">
                    {{ o.balance > 0 ? `${formatMyr(o.balance)} owed` : 'Paid' }}
                  </span>
                </div>
              </NuxtLink>
            </div>
          </template>
        </section>

        <!-- Payments received -->
        <section>
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">
            Payments received ({{ detail.payments.length }})
          </p>
          <p v-if="!detail.payments.length" class="text-[13px] rounded-2xl border px-5 py-4" :style="{ ...cardStyle, color: 'var(--color-text-tertiary)' }">
            No cash landed this month yet.
          </p>
          <template v-else>
            <div class="hidden md:block admin-table-card">
              <div class="overflow-x-auto">
                <table class="w-full text-left">
                  <thead>
                    <tr>
                      <th
                        v-for="h in ['Date', 'Client', 'Order', 'Method', 'Amount', 'Fee']" :key="h"
                        class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider"
                        :class="{ 'text-right': ['Amount', 'Fee'].includes(h) }"
                        style="color: var(--color-text-tertiary);">{{ h }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="p in detail.payments" :key="p.id" class="admin-table-row" @click="navigateTo(`/admin/payments/${p.id}`)">
                      <td class="px-4 py-3.5">
                        <p class="text-[13px]" style="color: var(--color-text);">{{ fmtDate(p.paid_at) }}</p>
                        <p class="text-[11px] font-mono" style="color: var(--color-text-tertiary);">{{ p.payment_number }}</p>
                      </td>
                      <td class="px-4 py-3.5 text-[13px]" style="color: var(--color-text-secondary);">{{ p.client?.name ?? '—' }}</td>
                      <td class="px-4 py-3.5">
                        <p class="text-[13px] font-mono" style="color: var(--color-text-secondary);">{{ p.order_number ?? '—' }}</p>
                        <p v-if="earlierOrderMonth(p)" class="text-[11px]" style="color: var(--color-text-tertiary);">from {{ earlierOrderMonth(p) }} order</p>
                      </td>
                      <td class="px-4 py-3.5 text-[13px]" style="color: var(--color-text-secondary);">{{ methodLabels[p.method] ?? p.method }}</td>
                      <td
                        class="px-4 py-3.5 text-right text-[13px] tabular-nums font-medium"
                        :style="{ color: p.amount < 0 ? 'var(--color-danger)' : 'var(--color-text)' }">
                        {{ formatMyr(p.amount) }}
                        <span v-if="p.amount < 0" class="block text-[11px] font-normal">Refund</span>
                      </td>
                      <td class="px-4 py-3.5 text-right text-[12px] tabular-nums" style="color: var(--color-text-tertiary);">
                        {{ p.fee > 0 ? formatMyr(p.fee) : '—' }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="md:hidden space-y-2.5">
              <NuxtLink
                v-for="p in detail.payments" :key="p.id" :to="`/admin/payments/${p.id}`"
                class="block rounded-xl border p-4 transition-opacity active:opacity-70" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
                <div class="flex items-start justify-between gap-3 mb-1">
                  <span class="text-[13px] font-semibold min-w-0 wrap-break-word" style="color: var(--color-text);">{{ p.client?.name ?? '—' }}</span>
                  <span
                    class="text-[13px] font-semibold tabular-nums shrink-0"
                    :style="{ color: p.amount < 0 ? 'var(--color-danger)' : 'var(--color-text)' }">{{ formatMyr(p.amount) }}</span>
                </div>
                <p class="text-[11px]" style="color: var(--color-text-tertiary);">
                  {{ fmtDate(p.paid_at) }} · {{ methodLabels[p.method] ?? p.method }} · {{ p.order_number ?? '—' }}<span v-if="earlierOrderMonth(p)"> (from {{ earlierOrderMonth(p) }})</span>
                </p>
              </NuxtLink>
            </div>
          </template>
        </section>

        <!-- By client -->
        <section>
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">
            By client ({{ detail.clients.length }})
          </p>
          <div class="hidden md:block admin-table-card">
            <div class="overflow-x-auto">
              <table class="w-full text-left">
                <thead>
                  <tr>
                    <th
                      v-for="h in ['Client', 'Orders', 'Booked', 'Collected']" :key="h"
                      class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider"
                      :class="{ 'text-right': h !== 'Client' }"
                      style="color: var(--color-text-tertiary);">{{ h }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="c in detail.clients" :key="c.id" class="admin-table-row" @click="navigateTo(`/admin/clients/${c.id}`)">
                    <td class="px-4 py-3.5 text-[13px] font-medium" style="color: var(--color-text);">{{ c.name }}</td>
                    <td class="px-4 py-3.5 text-right text-[13px] tabular-nums" style="color: var(--color-text-secondary);">{{ c.orders }}</td>
                    <td class="px-4 py-3.5 text-right text-[13px] tabular-nums" style="color: var(--color-text);">{{ formatMyr(c.booked) }}</td>
                    <td class="px-4 py-3.5 text-right text-[13px] tabular-nums" style="color: var(--color-text);">{{ formatMyr(c.collected) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <!-- Mobile: cards -->
          <div class="md:hidden space-y-2.5">
            <NuxtLink
              v-for="c in detail.clients" :key="c.id" :to="`/admin/clients/${c.id}`"
              class="block rounded-xl border p-4 transition-opacity active:opacity-70" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
              <div class="flex items-start justify-between gap-3 mb-3">
                <span class="text-[13px] font-semibold min-w-0 wrap-break-word" style="color: var(--color-text);">{{ c.name }}</span>
                <span class="text-[11px] shrink-0 tabular-nums" style="color: var(--color-text-tertiary);">{{ c.orders }} {{ c.orders === 1 ? 'order' : 'orders' }}</span>
              </div>
              <div class="pt-2 border-t grid grid-cols-2 gap-3 text-[12px] tabular-nums" :style="{ borderColor: 'var(--color-border)' }">
                <div>
                  <p class="text-[11px]" style="color: var(--color-text-tertiary);">Booked</p>
                  <p class="font-medium" style="color: var(--color-text);">{{ formatMyr(c.booked) }}</p>
                </div>
                <div class="text-right">
                  <p class="text-[11px]" style="color: var(--color-text-tertiary);">Collected</p>
                  <p class="font-medium" style="color: var(--color-text);">{{ formatMyr(c.collected) }}</p>
                </div>
              </div>
            </NuxtLink>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>
