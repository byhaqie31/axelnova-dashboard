<script setup lang="ts">
import QuotationBuilder from '~/components/admin/QuotationBuilder.vue'
import DetailedQuotationView from '~/components/admin/DetailedQuotationView.vue'

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
  expires_at: string | null
  updated_at: string | null
  updated_by: { id: number, name: string } | null
  public_token: string | null
  form_payload: Record<string, any> | null
  document: Record<string, any> | null
  addons: { key: string; label: string; amount_myr: string }[]
  referral_partner_id: number | null
  referrer: { name: string; relationship_tier: string; commission_pct: number } | null
  order_id: number | null
  order_number: string | null
}

interface QuotationInvoice {
  id: number
  invoice_number: string
  type: string
  status: string
  amount_total: string
  issued_at: string | null
}

const quotation = ref<Quotation | null>(null)
const loading = ref(true)
const error = ref('')
const statusLoading = ref(false)
const acceptLoading = ref(false)

useHead(() => ({
  title: quotation.value ? `${quotation.value.reference_code} — Admin` : 'Quotation — Admin',
}))

// A `seq` guard keeps the latest-issued fetch authoritative. `quiet` refreshes in
// the background (after a builder save) without the full-page loading flash.
let fetchSeq = 0
async function fetchQuotation(quiet = false) {
  const seq = ++fetchSeq
  if (!quiet) loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: Quotation }>(`/api/v1/admin/quotations/${route.params.id}`)
    if (seq !== fetchSeq) return
    quotation.value = res.data
  }
  catch {
    if (seq !== fetchSeq) return
    if (!quiet) error.value = 'Failed to load quotation.'
  }
  finally {
    if (seq === fetchSeq && !quiet) loading.value = false
  }
}

// Apply a fresh quotation the server already handed back (e.g. the send response)
// instead of refetching — instant, and invalidates any in-flight fetch.
function applyQuotation(data: Record<string, any>) {
  fetchSeq++
  quotation.value = data as Quotation
  loading.value = false
}

// Manage-client modal (correct a mis-matched client — edit details or re-link).
// The modal hands back only the contact patch; merge it onto the quotation.
const manageClientOpen = ref(false)
function onClientSaved(patch: Record<string, any>) {
  if (!quotation.value) return
  quotation.value = { ...quotation.value, ...patch } as Quotation
}

const isDraft = computed(() => quotation.value?.status === 'draft')
const isDetailed = computed(() => quotation.value?.document?.layout === 'detailed')

// Referral-attributed quotes let the founder confirm the commission % on accept —
// defaults to the referrer's tier estimate (or 10 if the nested referrer summary
// isn't loaded), clamped 5–15. Non-referral quotes never show or send this field.
const isReferralAttributed = computed(() => !!quotation.value?.referral_partner_id)
const commissionPct = ref(10)
watch(() => quotation.value?.referral_partner_id, () => {
  commissionPct.value = quotation.value?.referrer?.commission_pct ?? 10
}, { immediate: true })

async function acceptQuotation() {
  if (!quotation.value) return
  if (!(await confirm({
    title: 'Create an order from this quote?',
    message: `This accepts ${quotation.value.reference_code} and creates a new order — you can’t undo it here.`,
    confirmLabel: 'Proceed & create order',
  }))) return
  acceptLoading.value = true
  try {
    const body = isReferralAttributed.value ? { commission_pct: commissionPct.value } : undefined
    const res = await apiFetch<{ message: string; order_id: number; order_number: string }>(
      `/api/v1/admin/quotations/${quotation.value.id}/accept`, { method: 'POST', body },
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

// Invoices issued on this quotation's order — the quote → invoice cross-nav.
const invoices = ref<QuotationInvoice[]>([])
watch(() => quotation.value?.order_id, async (orderId) => {
  if (!orderId) { invoices.value = []; return }
  try {
    const res = await apiFetch<{ data: QuotationInvoice[] }>(`/api/v1/admin/invoices?order_id=${orderId}`)
    invoices.value = res.data
  }
  catch {
    invoices.value = []
  }
})

onMounted(fetchQuotation)

// `k` shorthand for the min–max estimate range only.
function fmtMyr(amount: string | number) {
  const n = Number(amount)
  return n >= 1000 ? `RM ${(n / 1000).toFixed(0)}k` : `RM ${n.toLocaleString()}`
}
// Precise — for exact single values (e.g. an add-on price).
function fmtMyrExact(amount: string | number) {
  return `RM ${Math.round(Number(amount) || 0).toLocaleString('en-US')}`
}
function fmtDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// The lifecycle is driven by actions, not a status picker: draft/sent/accepted by
// the builder + accept flow, rejected by the Reject action below, and expired
// automatically once a sent quote passes its expiry date.
const rejectLoading = ref(false)
async function rejectQuotation() {
  if (!quotation.value) return
  rejectLoading.value = true
  try {
    await apiFetch(`/api/v1/admin/quotations/${quotation.value.id}/status`, { method: 'POST', body: { status: 'rejected' } })
    quotation.value.status = 'rejected'
    toast.success('Quotation rejected', 'Moved to rejected. You can still re-open it as a draft.')
  }
  catch { toast.error('Couldn’t reject quotation', 'Something went wrong. Please try again.') }
  finally { rejectLoading.value = false }
}

// Custom validity date — editable on sent/expired quotes. Extending an expired
// quote re-activates it; the server keeps status and date in agreement.
const canEditExpiry = computed(() => ['sent', 'expired'].includes(quotation.value?.status ?? ''))
const expiryEditing = ref(false)
const expiryDraft = ref('')
const expiryLoading = ref(false)
function startEditExpiry() {
  expiryDraft.value = quotation.value?.expires_at ? quotation.value.expires_at.slice(0, 10) : ''
  expiryEditing.value = true
}
async function saveExpiry() {
  if (!quotation.value) return
  expiryLoading.value = true
  try {
    const res = await apiFetch<{ data: Record<string, any> }>(
      `/api/v1/admin/quotations/${quotation.value.id}/expiry`, { method: 'POST', body: { expires_at: expiryDraft.value || null } },
    )
    applyQuotation(res.data)
    expiryEditing.value = false
    toast.success('Validity updated', expiryDraft.value ? 'New expiry date saved.' : 'Expiry cleared — this quote no longer expires.')
  }
  catch { toast.error('Couldn’t update validity', 'Something went wrong. Please try again.') }
  finally { expiryLoading.value = false }
}

// Soft-delete flow (shared composable) — same confirm dialog, 409 order-attached
// block, and linked-record cleanup as the list. On success we leave the detail page.
const {
  target: deleteTarget,
  blocked: deleteBlocked,
  deleting,
  open: openDelete,
  close: closeDelete,
  confirm: confirmDelete,
} = useQuotationDelete(() => navigateTo('/admin/quotations'))

// Confirm-before-act on Proceed & Create Order.
const { confirmOpen, confirmConfig, confirm, resolveConfirm } = useConfirm()

</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <NuxtLink
to="/admin/quotations" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
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
            <h1 class="text-[24px] font-bold tracking-tight" style="color: var(--color-text);">Edit {{ isDetailed ? 'detailed ' : '' }}draft quotation</h1>
          </div>
          <AdminStatusPill :status="quotation.status" size="md" />
        </div>
        <QuotationBuilder
          :quotation="quotation"
          @saved="() => fetchQuotation(true)"
          @sent="applyQuotation"
          @accepted="(orderId) => navigateTo(`/admin/orders/${orderId}`)"
          @delete="openDelete(quotation)"
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
              <div class="flex flex-col items-end gap-2">
                <AdminStatusPill :status="quotation.status" size="md" />
                <button
                  type="button" class="text-[12px] font-medium inline-flex items-center gap-1.5 transition-opacity hover:opacity-70"
                  :style="{ color: 'var(--color-accent)' }" @click="manageClientOpen = true">
                  <UIcon name="i-lucide-pencil" class="size-3" /> Manage client
                </button>
              </div>
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
              <div v-if="quotation.updated_by">
                <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Last updated by</p>
                <p class="text-[13px]" style="color: var(--color-text);">
                  {{ quotation.updated_by.name }}<span v-if="quotation.updated_at" style="color: var(--color-text-tertiary);"> · {{ fmtDate(quotation.updated_at) }}</span>
                </p>
              </div>
              <div v-if="quotation.expires_at || canEditExpiry">
                <p class="text-[11px] font-medium uppercase tracking-wider mb-1 flex items-center gap-1.5" style="color: var(--color-text-tertiary);">
                  {{ quotation.status === 'expired' ? 'Expired' : 'Valid until' }}
                  <button v-if="canEditExpiry && !expiryEditing" type="button" class="inline-flex transition-opacity hover:opacity-60" :style="{ color: 'var(--color-accent)' }" aria-label="Edit validity date" @click="startEditExpiry">
                    <UIcon name="i-lucide-pencil" class="size-3" />
                  </button>
                </p>
                <div v-if="expiryEditing" class="flex items-center gap-1.5">
                  <input v-model="expiryDraft" type="date" class="contact-input text-[12px] py-1 px-2" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" >
                  <button type="button" class="inline-flex items-center justify-center size-7 rounded-lg shrink-0 transition-colors" :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }" :disabled="expiryLoading" aria-label="Save validity date" @click="saveExpiry">
                    <UIcon :name="expiryLoading ? 'i-lucide-loader-circle' : 'i-lucide-check'" class="size-3.5" :class="{ 'animate-spin': expiryLoading }" />
                  </button>
                  <button type="button" class="inline-flex items-center justify-center size-7 rounded-lg shrink-0 transition-colors hover:bg-(--color-bg-secondary)" :style="{ color: 'var(--color-text-tertiary)' }" aria-label="Cancel" @click="expiryEditing = false">
                    <UIcon name="i-lucide-x" class="size-3.5" />
                  </button>
                </div>
                <p v-else class="text-[13px]" :style="{ color: quotation.status === 'expired' ? 'var(--color-danger)' : 'var(--color-text)' }">{{ quotation.expires_at ? fmtDate(quotation.expires_at) : 'No expiry' }}</p>
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
                <span class="text-[13px] font-semibold" style="color: var(--color-text);">{{ fmtMyrExact(addon.amount_myr) }}</span>
              </div>
            </div>
          </div>

          <AdminScopeDetails :scope="quotation.form_payload" variant="card" />
          </template>
        </div>

        <div class="lg:sticky lg:top-20 space-y-4">
          <div v-if="quotation.public_token" class="rounded-2xl border p-5 space-y-3" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Document</p>
            <button type="button" class="btn-pill btn-pill-ghost w-full justify-center text-[13px]" @click="viewPdf">View PDF</button>
          </div>

          <!-- Invoices issued on this quotation's order — cross-navigation. -->
          <div v-if="quotation.order_id" class="rounded-2xl border p-5" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Invoices</p>
            <div v-if="invoices.length" class="space-y-1.5">
              <NuxtLink
                v-for="inv in invoices" :key="inv.id" :to="`/admin/invoices/${inv.id}`"
                class="flex items-center justify-between gap-2 rounded-xl border px-3 py-2 transition-opacity hover:opacity-75"
                :style="{ borderColor: 'var(--color-border)' }">
                <div class="min-w-0">
                  <span class="font-mono text-[12px] font-semibold block" style="color: var(--color-accent);">{{ inv.invoice_number }}</span>
                  <span class="text-[11px] capitalize" style="color: var(--color-text-tertiary);">{{ inv.type }}</span>
                </div>
                <div class="text-right shrink-0">
                  <span class="text-[12px] font-semibold tabular-nums block" style="color: var(--color-text);">{{ fmtMyrExact(inv.amount_total) }}</span>
                  <AdminStatusPill :status="inv.status" />
                </div>
              </NuxtLink>
            </div>
            <p v-else class="text-[12px]" style="color: var(--color-text-tertiary);">None issued yet.</p>
            <NuxtLink
              v-if="quotation.order_number" :to="`/admin/orders/${quotation.order_id}`"
              class="mt-3 inline-flex items-center gap-1.5 text-[12px] font-medium" :style="{ color: 'var(--color-accent)' }">
              Order {{ quotation.order_number }} <UIcon name="i-lucide-arrow-right" class="size-3.5" />
            </NuxtLink>
          </div>

          <div class="rounded-2xl border p-5 space-y-3" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Actions</p>

            <!-- Outcomes for a live sent quote: accept (→ order) or reject. Expiry is automatic. -->
            <template v-if="quotation.status === 'sent'">
              <div v-if="isReferralAttributed">
                <label for="commission-pct" class="text-[11px] font-medium uppercase tracking-wider mb-1 block" style="color: var(--color-text-tertiary);">
                  Commission % <span v-if="quotation.referrer" class="normal-case font-normal" style="color: var(--color-text-secondary);">— {{ quotation.referrer.name }}</span>
                </label>
                <input
id="commission-pct" v-model.number="commissionPct" type="number" min="5" max="15" class="contact-input w-full text-[13px]"
                  :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" >
              </div>
              <button class="btn-pill btn-pill-accent w-full justify-center text-[13px]" :disabled="acceptLoading || rejectLoading" @click="acceptQuotation">
                {{ acceptLoading ? 'Creating order…' : 'Proceed & Create Order' }}
              </button>
              <button class="btn-pill btn-pill-ghost w-full justify-center text-[13px]" :style="{ color: 'var(--color-danger)' }" :disabled="acceptLoading || rejectLoading" @click="rejectQuotation">
                {{ rejectLoading ? 'Rejecting…' : 'Reject Quotation' }}
              </button>
            </template>

            <!-- Re-open a sent / rejected / expired quote for editing. -->
            <button v-if="quotation.status !== 'accepted'" class="btn-pill btn-pill-silver w-full justify-center text-[13px]" :disabled="statusLoading" @click="revertToDraft">
              {{ statusLoading ? 'Editing…' : 'Mark as Draft' }}
            </button>

            <a :href="`mailto:${quotation.email}?subject=Re: your quote ${quotation.reference_code}`" class="btn-pill btn-pill-ghost w-full justify-center text-[13px]">Email Client</a>
            <a
v-if="quotation.phone" :href="`https://wa.me/${quotation.phone.replace(/\D/g, '')}?text=Hi%20${encodeURIComponent(quotation.name)}%2C%20about%20your%20quote%20${quotation.reference_code}.`"
              target="_blank" rel="noopener" class="btn-pill btn-pill-success w-full justify-center text-[13px]">WhatsApp</a>

            <!-- Delete — subdued danger, separated so it never competes with the primary CTA. -->
            <div class="pt-3 mt-1 border-t" :style="{ borderColor: 'var(--color-border)' }">
              <button type="button" class="btn-pill btn-pill-ghost w-full justify-center text-[13px]" :style="{ color: 'var(--color-danger)' }" @click="openDelete(quotation)">
                <UIcon name="i-lucide-trash-2" class="size-3.5" /> Delete quotation
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Delete confirmation — shared dialog (soft delete, or the order-attached block). -->
    <AdminQuotationDeleteDialog
      :target="deleteTarget" :blocked="deleteBlocked" :deleting="deleting"
      @cancel="closeDelete" @confirm="confirmDelete" />

    <!-- Confirm gate for Proceed & Create Order. -->
    <AdminConfirmDialog :open="confirmOpen" :config="confirmConfig" @resolve="resolveConfirm" />

    <AdminManageClientModal
      v-if="quotation"
      :open="manageClientOpen"
      context="quotation"
      :record-id="quotation.id"
      :client="quotation.client_id ? { id: quotation.client_id, name: quotation.name, email: quotation.email, phone: quotation.phone, company: quotation.company } : null"
      @close="manageClientOpen = false"
      @saved="onClientSaved" />
  </div>
</template>
