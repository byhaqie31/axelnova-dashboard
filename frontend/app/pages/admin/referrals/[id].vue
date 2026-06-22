<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()

interface Referral {
  id: number
  referrer_name: string
  referrer_email: string
  referrer_phone: string | null
  business_name: string
  business_contact_name: string | null
  business_email: string | null
  business_phone: string | null
  relationship_tier: 'cold' | 'warm' | 'closed'
  commission_tier_pct: number
  notes: string | null
  status: string
  agreed_terms: boolean
  linked_order_id: number | null
  order_number: string | null
  created_at: string
}

interface OrderRow {
  id: number
  order_number: string
  name: string | null
  status: string
}

const referral = ref<Referral | null>(null)
const loading = ref(true)
const error = ref('')
const statusLoading = ref(false)
const actionMessage = ref('')

const linkOpen = ref(false)
const orders = ref<OrderRow[]>([])
const ordersLoading = ref(false)
const linkLoading = ref(false)

const tierLabels: Record<string, string> = { cold: 'Cold lead', warm: 'Warm intro', closed: 'Closed referral' }

useHead(() => ({
  title: referral.value ? `${referral.value.referrer_name} — Referral` : 'Referral — Admin',
}))

async function fetchReferral() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: Referral }>(`/api/v1/admin/referrals/${route.params.id}`)
    referral.value = res.data
  }
  catch {
    error.value = 'Failed to load referral.'
  }
  finally {
    loading.value = false
  }
}

async function updateStatus(status: string) {
  if (!referral.value) return
  statusLoading.value = true
  try {
    await apiFetch(`/api/v1/admin/referrals/${referral.value.id}/status`, {
      method: 'POST',
      body: { status },
    })
    referral.value.status = status
    actionMessage.value = `Status updated to "${status}".`
  }
  catch {
    actionMessage.value = 'Failed to update status.'
  }
  finally {
    statusLoading.value = false
  }
}

async function openLinkPanel() {
  linkOpen.value = !linkOpen.value
  if (linkOpen.value && !orders.value.length) {
    ordersLoading.value = true
    try {
      const res = await apiFetch<{ data: OrderRow[] }>('/api/v1/admin/orders')
      orders.value = res.data
    }
    catch {
      actionMessage.value = 'Failed to load orders.'
    }
    finally {
      ordersLoading.value = false
    }
  }
}

async function linkOrder(orderId: number, orderNumber: string) {
  if (!referral.value) return
  linkLoading.value = true
  try {
    await apiFetch(`/api/v1/admin/referrals/${referral.value.id}/link-order`, {
      method: 'POST',
      body: { order_id: orderId },
    })
    referral.value.status = 'converted'
    referral.value.linked_order_id = orderId
    referral.value.order_number = orderNumber
    linkOpen.value = false
    actionMessage.value = `Linked to ${orderNumber} — marked converted.`
  }
  catch {
    actionMessage.value = 'Failed to link order.'
  }
  finally {
    linkLoading.value = false
  }
}

onMounted(fetchReferral)

function fmtDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// 'converted' is reached only by linking an order — never a manual status button.
const statusOptions = ['new', 'contacted', 'qualified', 'rejected']
const statusLabels: Record<string, string> = {
  new: 'New', contacted: 'Contacted', qualified: 'Qualified', rejected: 'Rejected',
}
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <NuxtLink to="/admin/referrals" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All referrals
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
    <p v-else-if="error" style="color: var(--color-danger);">{{ error }}</p>

    <div v-else-if="referral" class="grid lg:grid-cols-[1fr_300px] gap-8 items-start">

      <div class="space-y-6">

        <!-- Header -->
        <div class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <div class="flex items-start justify-between flex-wrap gap-4 mb-5">
            <div>
              <p class="text-[22px] font-bold tracking-tight" style="color: var(--color-text);">{{ referral.referrer_name }}</p>
              <p class="text-[14px] mt-0.5" style="color: var(--color-text-secondary);">referred <span style="color: var(--color-text);">{{ referral.business_name }}</span></p>
            </div>
            <AdminStatusPill :status="referral.status" size="md" />
          </div>
          <div class="grid sm:grid-cols-3 gap-4 pt-4 border-t" style="border-color: var(--color-border);">
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Tier</p>
              <p class="text-[13px] font-medium" style="color: var(--color-text);">{{ tierLabels[referral.relationship_tier] }}</p>
            </div>
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Commission</p>
              <p class="text-[13px] font-semibold tabular-nums" style="color: var(--color-accent);">{{ referral.commission_tier_pct }}%</p>
            </div>
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Submitted</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ fmtDate(referral.created_at) }}</p>
            </div>
          </div>
        </div>

        <!-- Referrer contact -->
        <div class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Referrer</p>
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <p class="text-[11px] mb-0.5" style="color: var(--color-text-tertiary);">Email</p>
              <a :href="`mailto:${referral.referrer_email}`" class="text-[13px] font-medium" style="color: var(--color-accent);">{{ referral.referrer_email }}</a>
            </div>
            <div v-if="referral.referrer_phone">
              <p class="text-[11px] mb-0.5" style="color: var(--color-text-tertiary);">Phone</p>
              <a :href="`tel:${referral.referrer_phone}`" class="text-[13px] font-medium" style="color: var(--color-text);">{{ referral.referrer_phone }}</a>
            </div>
          </div>
        </div>

        <!-- Business -->
        <div class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Business referred</p>
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <p class="text-[11px] mb-0.5" style="color: var(--color-text-tertiary);">Name</p>
              <p class="text-[13px] font-medium" style="color: var(--color-text);">{{ referral.business_name }}</p>
            </div>
            <div v-if="referral.business_contact_name">
              <p class="text-[11px] mb-0.5" style="color: var(--color-text-tertiary);">Contact</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ referral.business_contact_name }}</p>
            </div>
            <div v-if="referral.business_email">
              <p class="text-[11px] mb-0.5" style="color: var(--color-text-tertiary);">Email</p>
              <a :href="`mailto:${referral.business_email}`" class="text-[13px] font-medium" style="color: var(--color-accent);">{{ referral.business_email }}</a>
            </div>
            <div v-if="referral.business_phone">
              <p class="text-[11px] mb-0.5" style="color: var(--color-text-tertiary);">Phone</p>
              <a :href="`tel:${referral.business_phone}`" class="text-[13px] font-medium" style="color: var(--color-text);">{{ referral.business_phone }}</a>
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div v-if="referral.notes" class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Notes</p>
          <p class="text-[13px] leading-relaxed whitespace-pre-line" style="color: var(--color-text);">{{ referral.notes }}</p>
        </div>

      </div>

      <!-- Sidebar -->
      <div class="lg:sticky lg:top-20 space-y-4">

        <!-- Status -->
        <div class="rounded-2xl border p-5"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Update status</p>
          <div class="flex flex-wrap gap-2">
            <button v-for="s in statusOptions" :key="s" type="button"
              class="status-pill status-pill-button"
              :class="{ 'opacity-50': statusLoading }"
              :data-status="referral.status === s ? s : ''"
              :data-active="referral.status === s"
              :disabled="statusLoading || referral.status === s || referral.status === 'converted'"
              @click="updateStatus(s)">
              {{ statusLabels[s] }}
            </button>
          </div>
          <p v-if="referral.status === 'converted'" class="text-[11px] mt-3" style="color: var(--color-text-tertiary);">
            Converted referrals are locked — they're linked to an order.
          </p>
        </div>

        <!-- Convert / link order -->
        <div class="rounded-2xl border p-5 space-y-3"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Conversion</p>

          <div v-if="referral.linked_order_id" class="rounded-xl border px-4 py-3"
            :style="{ background: 'var(--color-bg-secondary)', borderColor: 'var(--color-border)' }">
            <p class="text-[11px]" style="color: var(--color-text-tertiary);">Linked order</p>
            <NuxtLink :to="`/admin/orders/${referral.linked_order_id}`" class="text-[13px] font-mono font-medium" style="color: var(--color-accent);">
              {{ referral.order_number ?? `#${referral.linked_order_id}` }}
            </NuxtLink>
          </div>

          <template v-else>
            <button type="button" class="btn-pill btn-pill-accent w-full justify-center text-[13px]"
              :disabled="ordersLoading"
              @click="openLinkPanel">
              {{ linkOpen ? 'Close' : 'Link to an order →' }}
            </button>
            <div v-if="linkOpen" class="rounded-xl border p-1.5 max-h-64 overflow-y-auto"
              :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }">
              <p v-if="ordersLoading" class="text-[12px] px-2 py-2" style="color: var(--color-text-tertiary);">Loading orders…</p>
              <p v-else-if="!orders.length" class="text-[12px] px-2 py-2" style="color: var(--color-text-tertiary);">No orders yet.</p>
              <button v-for="o in orders" :key="o.id" type="button"
                class="w-full text-left px-2.5 py-2 rounded-md transition-colors hover:bg-(--color-bg-secondary)"
                :disabled="linkLoading"
                @click="linkOrder(o.id, o.order_number)">
                <span class="text-[12px] font-mono font-medium" style="color: var(--color-accent);">{{ o.order_number }}</span>
                <span v-if="o.name" class="text-[12px] ml-2" style="color: var(--color-text-secondary);">{{ o.name }}</span>
              </button>
            </div>
            <p class="text-[11px]" style="color: var(--color-text-tertiary);">
              Linking marks the referral converted and locks its commission tier.
            </p>
          </template>
        </div>

        <!-- Actions -->
        <div class="rounded-2xl border p-5 space-y-3"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Actions</p>

          <a :href="`mailto:${referral.referrer_email}?subject=Your%20referral%20to%20Axel%20Nova`"
            class="btn-pill btn-pill-ghost w-full justify-center text-[13px]">
            Email referrer
          </a>

          <a v-if="referral.referrer_phone"
            :href="`https://wa.me/${referral.referrer_phone.replace(/\D/g, '')}?text=Hi%20${encodeURIComponent(referral.referrer_name)}%2C%20thanks%20for%20referring%20${encodeURIComponent(referral.business_name)}.`"
            target="_blank" rel="noopener"
            class="btn-pill btn-pill-ghost w-full justify-center text-[13px]">
            WhatsApp
          </a>
        </div>

        <p v-if="actionMessage" class="text-[12px] text-center px-3" style="color: var(--color-text-secondary);">
          {{ actionMessage }}
        </p>

        <!-- Audit -->
        <div class="rounded-xl border px-4 py-3.5 space-y-2"
          :style="{ background: 'var(--color-bg-secondary)', borderColor: 'var(--color-border)' }">
          <div class="flex justify-between">
            <span class="text-[11px]" style="color: var(--color-text-tertiary);">Submitted</span>
            <span class="text-[11px]" style="color: var(--color-text-secondary);">{{ fmtDate(referral.created_at) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-[11px]" style="color: var(--color-text-tertiary);">Agreed to terms</span>
            <span class="text-[11px]" style="color: var(--color-text-secondary);">{{ referral.agreed_terms ? 'Yes' : 'No' }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
