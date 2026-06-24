<script setup lang="ts">
import QuotationBuilder from '~/components/admin/QuotationBuilder.vue'
import DetailedQuotationBuilder from '~/components/admin/DetailedQuotationBuilder.vue'
import DetailedQuotationView from '~/components/admin/DetailedQuotationView.vue'
import type { QuoteExpandSeed } from '~/composables/quoteScope'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

interface Quotation {
  id: number
  reference_code: string
  source: string
  client_id: number | null
  name: string
  email: string
  phone: string | null
  company: string | null
  package_key: string | null
  estimate_min_myr: string
  estimate_max_myr: string
  estimate_eta_value: number
  estimate_eta_unit: 'hour' | 'day' | 'week' | 'month'
  status: string
  submitted_at: string
  viewed_at: string | null
  sent_at: string | null
  public_token: string | null
  form_payload: Record<string, any> | null
  document: Record<string, any> | null
  addons: { key: string; label: string; amount_myr: string }[]
}

const quotation = ref<Quotation | null>(null)
const loading = ref(true)
const error = ref('')
const statusLoading = ref(false)
const acceptLoading = ref(false)

useHead(() => ({
  title: quotation.value ? `${quotation.value.reference_code} — Admin` : 'Quotation — Admin',
}))

// Sending fires two refreshes (a `saved` mid-send, then `sent`); guard so the
// latest-issued fetch always wins and the page can't settle on a stale status.
let fetchSeq = 0
async function fetchQuotation() {
  const seq = ++fetchSeq
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: Quotation }>(`/api/v1/admin/quotations/${route.params.id}`)
    if (seq !== fetchSeq) return
    quotation.value = res.data
  }
  catch {
    if (seq !== fetchSeq) return
    error.value = 'Failed to load quotation.'
  }
  finally {
    if (seq === fetchSeq) loading.value = false
  }
}

const isDraft = computed(() => quotation.value?.status === 'draft')
const isDetailed = computed(() => quotation.value?.document?.layout === 'detailed')

// In-place upgrade: while editing a standard draft, the admin can switch to the
// detailed builder (seeded from the line items) without leaving the page. The
// override wins until the next refetch, after which document.layout reflects the
// saved choice and keeps the detailed builder mounted on its own.
const layoutOverride = ref<'detailed' | null>(null)
const expandSeed = ref<QuoteExpandSeed | null>(null)
const showDetailedBuilder = computed(() => layoutOverride.value === 'detailed' || isDetailed.value)
function onExpandToDetailed(s: QuoteExpandSeed) {
  expandSeed.value = s
  layoutOverride.value = 'detailed'
}
function onBuilderSaved() {
  layoutOverride.value = null
  expandSeed.value = null
  fetchQuotation()
}

async function updateStatus(status: string) {
  if (!quotation.value) return
  statusLoading.value = true
  try {
    await apiFetch(`/api/v1/admin/quotations/${quotation.value.id}/status`, { method: 'POST', body: { status } })
    quotation.value.status = status
    toast.success('Status updated', `Quotation set to ${statusLabels[status] ?? status}.`)
  }
  catch { toast.error('Couldn’t update status', 'Something went wrong. Please try again.') }
  finally { statusLoading.value = false }
}

async function acceptQuotation() {
  if (!quotation.value) return
  acceptLoading.value = true
  try {
    const res = await apiFetch<{ message: string; order_id: number; order_number: string }>(
      `/api/v1/admin/quotations/${quotation.value.id}/accept`, { method: 'POST' },
    )
    toast.success('Order created', `${res.order_number} created from this quotation.`)
    navigateTo(`/admin/orders/${res.order_id}`)
  }
  catch { toast.error('Couldn’t accept quotation', 'Something went wrong. Please try again.') }
  finally { acceptLoading.value = false }
}

// Re-open a sent/rejected/expired quote for editing. Flipping to draft swaps the
// read view back to the builder (isDraft) and re-enables the edit endpoint.
async function revertToDraft() {
  if (!quotation.value) return
  statusLoading.value = true
  try {
    await apiFetch(`/api/v1/admin/quotations/${quotation.value.id}/status`, { method: 'POST', body: { status: 'draft' } })
    quotation.value.status = 'draft'
    toast.success('Back to draft', 'Edit and re-send when you’re ready.')
  }
  catch { toast.error('Couldn’t move to draft', 'Something went wrong. Please try again.') }
  finally { statusLoading.value = false }
}

function viewPdf() {
  if (!quotation.value?.public_token) return
  window.open(`${window.location.origin}/documents/${quotation.value.public_token}/pdf`, '_blank', 'noopener')
}

onMounted(fetchQuotation)

function fmtMyr(amount: string | number) {
  const n = Number(amount)
  return n >= 1000 ? `RM ${(n / 1000).toFixed(0)}k` : `RM ${n.toLocaleString()}`
}
function fmtDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// Manual transitions; draft/sent/accepted are driven by the builder flow.
const statusOptions = ['rejected', 'expired']
const statusLabels: Record<string, string> = {
  rejected: 'Rejected', expired: 'Expired',
}

const scopeFields = computed(() => {
  if (!quotation.value?.form_payload) return []
  const p = quotation.value.form_payload
  const rows: { label: string; value: unknown }[] = []
  const skip = new Set(['package_key', 'modifiers', 'addon_keys', 'rush', 'breakdown', 'category_key'])
  for (const [k, v] of Object.entries(p)) {
    if (skip.has(k) || v === '' || v === null || (Array.isArray(v) && !v.length)) continue
    rows.push({ label: k.replace(/_/g, ' '), value: v })
  }
  return rows
})
</script>

<template>
  <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <NuxtLink to="/admin/quotations" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All quotations
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
    <p v-else-if="error" style="color: var(--color-danger);">{{ error }}</p>

    <template v-else-if="quotation">
      <!-- Draft → builder -->
      <template v-if="isDraft">
        <div class="mb-8 flex items-center justify-between flex-wrap gap-3">
          <div>
            <p class="font-mono text-[18px] font-bold" style="color: var(--color-accent);">{{ quotation.reference_code }}</p>
            <h1 class="text-[24px] font-bold tracking-tight" style="color: var(--color-text);">Edit {{ showDetailedBuilder ? 'detailed ' : '' }}draft quotation</h1>
          </div>
          <AdminStatusPill :status="quotation.status" size="md" />
        </div>
        <DetailedQuotationBuilder
          v-if="showDetailedBuilder"
          :quotation="quotation"
          :seed="expandSeed"
          @saved="onBuilderSaved"
          @sent="fetchQuotation"
          @accepted="(orderId) => navigateTo(`/admin/orders/${orderId}`)"
        />
        <QuotationBuilder
          v-else
          :quotation="quotation"
          @saved="fetchQuotation"
          @sent="fetchQuotation"
          @accepted="(orderId) => navigateTo(`/admin/orders/${orderId}`)"
          @expand="onExpandToDetailed"
        />
      </template>

      <!-- Non-draft → read view -->
      <div v-else class="grid lg:grid-cols-[1fr_300px] gap-8 items-start">
        <div class="space-y-6">
          <div class="rounded-2xl border p-6" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <div class="flex items-start justify-between flex-wrap gap-4 mb-5">
              <div>
                <p class="font-mono text-[20px] font-bold mb-1" style="color: var(--color-accent);">{{ quotation.reference_code }}</p>
                <p class="text-[22px] font-bold tracking-tight" style="color: var(--color-text);">{{ quotation.name }}</p>
                <p v-if="quotation.company" class="text-[14px] mt-0.5" style="color: var(--color-text-secondary);">{{ quotation.company }}</p>
              </div>
              <AdminStatusPill :status="quotation.status" size="md" />
            </div>
            <div class="grid sm:grid-cols-3 gap-4 pt-4 border-t" style="border-color: var(--color-border);">
              <div>
                <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Email</p>
                <a :href="`mailto:${quotation.email}`" class="text-[13px] font-medium" style="color: var(--color-accent);">{{ quotation.email }}</a>
              </div>
              <div v-if="quotation.phone">
                <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Phone</p>
                <a :href="`tel:${quotation.phone}`" class="text-[13px] font-medium" style="color: var(--color-text);">{{ quotation.phone }}</a>
              </div>
              <div>
                <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Sent</p>
                <p class="text-[13px]" style="color: var(--color-text);">{{ fmtDate(quotation.sent_at) }}</p>
              </div>
            </div>
          </div>

          <!-- Detailed → composed document view; standard → estimate + scope cards -->
          <DetailedQuotationView v-if="isDetailed && quotation.document?.payload" :payload="quotation.document.payload" />

          <template v-else>
          <div class="rounded-2xl border p-6" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Estimate</p>
            <p class="text-[30px] font-bold tracking-tight mb-1" style="color: var(--color-text);">
              {{ fmtMyr(quotation.estimate_min_myr) }} – {{ fmtMyr(quotation.estimate_max_myr) }}
            </p>
            <p class="text-[13px]" style="color: var(--color-text-secondary);">
              {{ formatEta(quotation.estimate_eta_value, quotation.estimate_eta_unit) }} ·
              Package: <code class="font-mono" style="color: var(--color-text);">{{ quotation.package_key ?? '—' }}</code>
            </p>
          </div>

          <div v-if="quotation.document?.items?.length" class="rounded-2xl border p-6" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Quotation line items</p>
            <div class="space-y-2">
              <div v-for="(it, i) in quotation.document.items" :key="i" class="flex justify-between items-baseline gap-4">
                <span class="text-[13px]" style="color: var(--color-text);">{{ it.title }}<span v-if="it.qty > 1" class="text-[12px]" style="color: var(--color-text-tertiary);"> × {{ it.qty }}</span></span>
                <span class="text-[13px] font-semibold tabular-nums whitespace-nowrap" style="color: var(--color-text);">RM {{ ((Number(it.qty) || 0) * (Number(it.rate) || 0)).toLocaleString() }}</span>
              </div>
            </div>
          </div>

          <div v-if="quotation.addons?.length" class="rounded-2xl border p-6" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Add-ons</p>
            <div class="space-y-2">
              <div v-for="addon in quotation.addons" :key="addon.key" class="flex justify-between items-center">
                <span class="text-[13px]" style="color: var(--color-text);">{{ addon.label }}</span>
                <span class="text-[13px] font-semibold" style="color: var(--color-text);">{{ fmtMyr(addon.amount_myr) }}</span>
              </div>
            </div>
          </div>

          <div v-if="scopeFields.length" class="rounded-2xl border p-6" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Scope details</p>
            <div class="grid sm:grid-cols-2 gap-x-8 gap-y-3">
              <div v-for="row in scopeFields" :key="row.label">
                <p class="text-[11px] capitalize mb-0.5" style="color: var(--color-text-tertiary);">{{ row.label }}</p>
                <p class="text-[13px]" style="color: var(--color-text);">{{ Array.isArray(row.value) ? row.value.join(', ') : String(row.value) }}</p>
              </div>
            </div>
          </div>
          </template>
        </div>

        <div class="lg:sticky lg:top-20 space-y-4">
          <div v-if="quotation.public_token" class="rounded-2xl border p-5 space-y-3" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Document</p>
            <button type="button" class="btn-pill btn-pill-ghost w-full justify-center text-[13px]" @click="viewPdf">View PDF</button>
          </div>

          <div class="rounded-2xl border p-5" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Update status</p>
            <div class="flex flex-wrap gap-2">
              <button v-for="s in statusOptions" :key="s" type="button"
                class="status-pill status-pill-button" :class="{ 'opacity-50': statusLoading }"
                :data-status="quotation.status === s ? s : ''" :data-active="quotation.status === s"
                :disabled="statusLoading || quotation.status === s" @click="updateStatus(s)">
                {{ statusLabels[s] }}
              </button>
            </div>
          </div>

          <div class="rounded-2xl border p-5 space-y-3" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Actions</p>
            <button v-if="quotation.status !== 'accepted'" class="btn-pill btn-pill-silver w-full justify-center text-[13px]" :disabled="statusLoading" @click="revertToDraft">
              {{ statusLoading ? 'Moving…' : 'Move back to draft' }}
            </button>
            <a :href="`mailto:${quotation.email}?subject=Re: your quote ${quotation.reference_code}`" class="btn-pill btn-pill-ghost w-full justify-center text-[13px]">Reply by email</a>
            <a v-if="quotation.phone" :href="`https://wa.me/${quotation.phone.replace(/\D/g, '')}?text=Hi%20${encodeURIComponent(quotation.name)}%2C%20about%20your%20quote%20${quotation.reference_code}.`"
              target="_blank" rel="noopener" class="btn-pill btn-pill-success w-full justify-center text-[13px]">WhatsApp</a>
            <button v-if="quotation.status !== 'accepted'" class="btn-pill btn-pill-accent w-full justify-center text-[13px]" :disabled="acceptLoading" @click="acceptQuotation">
              {{ acceptLoading ? 'Creating order…' : 'Proceed & Create Order' }}
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
