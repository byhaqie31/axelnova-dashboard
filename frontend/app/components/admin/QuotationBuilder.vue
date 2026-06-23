<script setup lang="ts">
import QuoteScopeFields from '~/components/shared/QuoteScopeFields.vue'
import type { QuoteScopeState } from '~/composables/quoteScope'
import type { EstimateResult } from '~/composables/usePricingEngine'
import { defaultQuoteScope, scopeToPayload } from '~/composables/quoteScope'

interface QuotationLike {
  id: number
  reference_code: string
  status: string
  public_token?: string | null
  client_id: number | null
  name: string
  email: string
  phone: string | null
  company: string | null
  package_key: string | null
  form_payload: Record<string, any> | null
  document: Record<string, any> | null
}

const props = defineProps<{
  quotation?: QuotationLike | null
  inquiryId?: number | null
}>()

const emit = defineEmits<{
  saved: [id: number]
  sent: []
  accepted: [orderId: number]
}>()

const { apiFetch } = useAdminAuth()
const { config, loadConfig, fmtMyr, formatEta } = usePricingEngine()
const toast = useAdminToast()

const isEdit = computed(() => !!props.quotation)

// ── Client ──────────────────────────────────────────────────────────────────
const client = reactive({
  mode: 'search' as 'search' | 'new',
  client_id: null as number | null,
  name: '',
  email: '',
  phone: '',
  company: '',
})
const clientSearch = ref('')
const clientResults = ref<{ id: number; name: string; email: string; phone: string | null; company: string | null }[]>([])
const clientSearchOpen = ref(false)

let clientTimer: ReturnType<typeof setTimeout>
watch(clientSearch, (q) => {
  clearTimeout(clientTimer)
  if (!q || q.length < 2) { clientResults.value = []; return }
  clientTimer = setTimeout(async () => {
    try {
      const res = await apiFetch<{ data: typeof clientResults.value }>(`/api/v1/admin/clients?search=${encodeURIComponent(q)}`)
      clientResults.value = res.data
      clientSearchOpen.value = true
    }
    catch { clientResults.value = [] }
  }, 300)
})

function pickClient(c: typeof clientResults.value[number]) {
  client.mode = 'search'
  client.client_id = c.id
  client.name = c.name
  client.email = c.email
  client.phone = c.phone ?? ''
  client.company = c.company ?? ''
  clientSearch.value = ''
  clientResults.value = []
  clientSearchOpen.value = false
}

function newClient() {
  client.mode = 'new'
  client.client_id = null
  client.name = ''
  client.email = ''
  client.phone = ''
  client.company = ''
}

function clearClient() {
  client.client_id = null
  client.name = ''
  client.email = ''
  client.phone = ''
  client.company = ''
  client.mode = 'search'
}

// ── Scope + estimate ────────────────────────────────────────────────────────
const scope = reactive<QuoteScopeState>(defaultQuoteScope())
const estimate = ref<EstimateResult | null>(null)
const modifiers = ref<Record<string, boolean | number>>({})

// ── Document ────────────────────────────────────────────────────────────────
interface LineItem { title: string; desc: string; qty: number; unit: string; rate: number }
const doc = reactive({
  project: '',
  intro: '',
  items: [] as LineItem[],
  termsText: '',
  deposit_pct: 50,
})

const defaultTerms = [
  '50% deposit to commence; balance due on delivery before handover.',
  'Revisions are included as scoped per phase; further rounds are quoted separately.',
  'Third-party costs (domains, fonts, hosting) are billed at cost where applicable.',
]

const grandTotal = computed(() =>
  doc.items.reduce((s, i) => s + (Number(i.qty) || 0) * (Number(i.rate) || 0), 0),
)

function packageMeta(key: string): { name: string; tagline: string } {
  for (const c of config.value?.categories ?? []) {
    const p = c.packages.find(p => p.key === key)
    if (p) return { name: p.name, tagline: p.tagline }
  }
  return { name: key, tagline: '' }
}

function seedItems() {
  if (!config.value || !scope.packageKey) return
  const items: LineItem[] = []
  const base = config.value.base_packages[scope.packageKey]
  if (base) {
    const meta = packageMeta(scope.packageKey)
    items.push({ title: meta.name, desc: meta.tagline, qty: 1, unit: 'project', rate: base.max })
  }
  for (const k of scope.addonKeys) {
    const a = config.value.addons[k]
    if (a) items.push({ title: a.label, desc: '', qty: 1, unit: '', rate: a.amount })
  }
  doc.items = items
}

function addItem() {
  doc.items.push({ title: '', desc: '', qty: 1, unit: '', rate: 0 })
}
function removeItem(i: number) {
  doc.items.splice(i, 1)
}

// ── Hydrate (edit / inquiry prefill) ────────────────────────────────────────
function hydrateScope(fp: Record<string, any>, packageKey: string | null) {
  Object.assign(scope, defaultQuoteScope(), {
    categoryKey: fp.category_key ?? '',
    packageKey: packageKey ?? '',
    pages: fp.pages ?? scope.pages,
    languages: fp.languages ?? [],
    cms: fp.cms ?? false,
    bookingFlow: fp.booking_flow ?? false,
    modules: fp.modules ?? scope.modules,
    userRoles: fp.user_roles ?? scope.userRoles,
    realTime: fp.real_time ?? false,
    chartsComplexity: fp.charts_complexity ?? 'basic',
    screensCount: fp.screens_count ?? scope.screensCount,
    designSystem: fp.design_system ?? false,
    prototype: fp.prototype ?? false,
    componentsCount: fp.components_count ?? scope.componentsCount,
    pagesCount: fp.pages_count ?? scope.pagesCount,
    stateManagement: fp.state_management ?? false,
    testing: fp.testing ?? false,
    coreFeatures: fp.core_features ?? '',
    authMethods: fp.auth_methods ?? [],
    paymentMethod: fp.payment_method ?? '',
    adminPortal: fp.admin_portal ?? false,
    addonKeys: fp.addon_keys ?? [],
    rush: fp.rush ?? false,
  })
}

onMounted(async () => {
  await loadConfig()

  if (props.quotation) {
    const q = props.quotation
    client.mode = q.client_id ? 'search' : 'new'
    client.client_id = q.client_id
    client.name = q.name
    client.email = q.email
    client.phone = q.phone ?? ''
    client.company = q.company ?? ''
    hydrateScope(q.form_payload ?? {}, q.package_key)
    const d = q.document ?? {}
    doc.project = d.project ?? ''
    doc.intro = d.intro ?? ''
    doc.items = (d.items ?? []).map((it: any) => ({
      title: it.title ?? '', desc: it.desc ?? '', qty: Number(it.qty ?? 1), unit: it.unit ?? '', rate: Number(it.rate ?? 0),
    }))
    doc.termsText = (d.terms ?? defaultTerms).join('\n')
    doc.deposit_pct = d.deposit_pct ?? 50
  }
  else {
    doc.termsText = defaultTerms.join('\n')
    if (props.inquiryId) {
      try {
        const res = await apiFetch<{ data: any }>(`/api/v1/admin/inquiries/${props.inquiryId}`)
        const inq = res.data
        client.mode = 'new'
        client.name = inq.name ?? ''
        client.email = inq.email ?? ''
        client.phone = inq.phone ?? ''
        client.company = inq.company ?? ''
        doc.project = inq.project_type ? `${inq.project_type} project` : ''
        doc.intro = inq.message ?? ''
      }
      catch { /* prefill is best-effort */ }
    }
  }
})

// ── Actions ─────────────────────────────────────────────────────────────────
const saving = ref(false)
const sending = ref(false)
const accepting = ref(false)
const error = ref('')

const clientValid = computed(() => !!client.client_id || (client.name.trim().length >= 2 && client.email.includes('@')))
const canSave = computed(() => clientValid.value && !!scope.packageKey)

function buildPayload() {
  return {
    client_id: client.client_id,
    name: client.name || null,
    email: client.email || null,
    phone: client.phone || null,
    company: client.company || null,
    package_key: scope.packageKey,
    modifiers: modifiers.value,
    addon_keys: scope.addonKeys,
    rush: scope.rush,
    form_payload: { ...scopeToPayload(scope), category_key: scope.categoryKey },
    document: {
      project: doc.project || null,
      intro: doc.intro || null,
      items: doc.items.map(i => ({ title: i.title, desc: i.desc || null, qty: Number(i.qty) || 0, unit: i.unit || null, rate: Number(i.rate) || 0 })),
      terms: doc.termsText.split('\n').map(t => t.trim()).filter(Boolean),
      deposit_pct: Number(doc.deposit_pct) || 0,
      tax_rate: 0,
    },
    inquiry_id: props.inquiryId ?? null,
  }
}

// Persist without UI feedback — shared by the Save button and the send flow
// so sending doesn't fire two toasts ("saved" then "sent").
async function persist(): Promise<number | null> {
  if (!canSave.value) { error.value = 'Add a client and pick a package first.'; return null }
  saving.value = true
  error.value = ''
  try {
    const payload = buildPayload()
    const res = isEdit.value
      ? await apiFetch<{ data: any }>(`/api/v1/admin/quotations/${props.quotation!.id}`, { method: 'PUT', body: payload })
      : await apiFetch<{ data: any }>('/api/v1/admin/quotations', { method: 'POST', body: payload })
    const id = res.data.id
    emit('saved', id)
    return id
  }
  catch (e: any) {
    const errs = e?.data?.errors ? Object.values(e.data.errors).flat().join(' ') : ''
    error.value = errs || e?.data?.message || 'Failed to save quotation.'
    return null
  }
  finally {
    saving.value = false
  }
}

async function save(): Promise<number | null> {
  const id = await persist()
  if (id) toast.success(isEdit.value ? 'Changes saved' : 'Draft saved', 'Quotation stored. Preview the PDF or send it to the client.')
  else if (error.value) toast.error('Couldn’t save quotation', error.value)
  return id
}

async function sendToClient() {
  if (!isEdit.value) return
  sending.value = true
  error.value = ''
  try {
    const id = await persist()
    if (!id) { toast.error('Couldn’t send quotation', error.value || 'Save failed.'); return }
    await apiFetch(`/api/v1/admin/quotations/${id}/send`, { method: 'POST' })
    emit('sent')
    toast.success('Quotation sent', `PDF emailed to ${client.email || 'the client'}.`)
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Failed to send quotation.'
    toast.error('Couldn’t send quotation', error.value)
  }
  finally {
    sending.value = false
  }
}

async function accept() {
  if (!isEdit.value) return
  accepting.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ order_id: number }>(`/api/v1/admin/quotations/${props.quotation!.id}/accept`, { method: 'POST' })
    toast.success('Order created', 'Quotation accepted and converted to an order.')
    emit('accepted', res.order_id)
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Failed to accept quotation.'
    toast.error('Couldn’t accept quotation', error.value)
  }
  finally {
    accepting.value = false
  }
}

function viewPdf() {
  if (!props.quotation?.public_token) return
  window.open(`${window.location.origin}/api/documents/${props.quotation.public_token}/pdf`, '_blank', 'noopener')
}
</script>

<template>
  <div class="grid lg:grid-cols-[1fr_320px] gap-8 items-start">

    <div class="space-y-8">

      <!-- Client -->
      <section class="rounded-2xl border p-6" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <div class="flex items-center justify-between mb-4">
          <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">Client</p>
          <div class="flex gap-1.5">
            <button type="button" class="standard-pill" :style="client.mode === 'search'
              ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
              : { borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }"
              @click="clearClient">Existing</button>
            <button type="button" class="standard-pill" :style="client.mode === 'new'
              ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
              : { borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }"
              @click="newClient">New</button>
          </div>
        </div>

        <!-- Existing: search / selected -->
        <div v-if="client.mode === 'search'">
          <div v-if="client.client_id" class="flex items-center justify-between rounded-xl border px-4 py-3"
            :style="{ borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)' }">
            <div>
              <p class="text-[13px] font-medium" style="color: var(--color-text);">{{ client.name }}</p>
              <p class="text-[12px]" style="color: var(--color-text-secondary);">{{ client.email }}<span v-if="client.company"> · {{ client.company }}</span></p>
            </div>
            <button type="button" class="text-[12px]" style="color: var(--color-accent);" @click="clearClient">Change</button>
          </div>
          <div v-else class="relative">
            <input v-model="clientSearch" type="text" placeholder="Search clients by name or email…" class="contact-input w-full"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
            <ul v-if="clientResults.length" class="absolute z-20 left-0 right-0 mt-1.5 rounded-xl border p-1 max-h-60 overflow-y-auto"
              :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-card-hover)' }">
              <li v-for="c in clientResults" :key="c.id">
                <button type="button" class="w-full text-left px-2.5 py-2 rounded-md transition-colors hover:bg-(--color-bg-secondary)" @click="pickClient(c)">
                  <span class="text-[13px] font-medium" style="color: var(--color-text);">{{ c.name }}</span>
                  <span class="text-[12px] ml-2" style="color: var(--color-text-tertiary);">{{ c.email }}</span>
                </button>
              </li>
            </ul>
            <p class="text-[11px] mt-2" style="color: var(--color-text-tertiary);">Type at least 2 characters, or switch to <button type="button" class="underline" style="color: var(--color-accent);" @click="newClient">New</button>.</p>
          </div>
        </div>

        <!-- New client fields -->
        <div v-else class="grid sm:grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Name *</label>
            <input v-model="client.name" type="text" class="contact-input w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Email *</label>
            <input v-model="client.email" type="email" class="contact-input w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Phone</label>
            <input v-model="client.phone" type="tel" class="contact-input w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Company</label>
            <input v-model="client.company" type="text" class="contact-input w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
          </div>
        </div>
      </section>

      <!-- Package & scope -->
      <section class="rounded-2xl border p-6" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <p class="text-[11px] font-semibold uppercase tracking-widest mb-5" style="color: var(--color-text-tertiary);">Package &amp; scope</p>
        <QuoteScopeFields :state="scope" @update:estimate="estimate = $event" @update:modifiers="modifiers = $event" />
      </section>

      <!-- Quotation document -->
      <section class="rounded-2xl border p-6 space-y-6" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <div class="flex items-center justify-between">
          <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">Quotation document</p>
          <button type="button" class="btn-pill btn-pill-ghost text-[12px]" :disabled="!scope.packageKey" @click="seedItems">Seed line items from scope</button>
        </div>

        <div class="grid gap-4">
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Project title</label>
            <input v-model="doc.project" type="text" placeholder="e.g. Brand website — design & front-end build" class="contact-input w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Intro</label>
            <textarea v-model="doc.intro" rows="2" placeholder="One-line summary shown under the project title…" class="contact-input resize-none w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
          </div>
        </div>

        <!-- Line items -->
        <div>
          <div class="flex items-center justify-between mb-2">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Line items</label>
            <button type="button" class="text-[12px]" style="color: var(--color-accent);" @click="addItem">+ Add line</button>
          </div>
          <div v-if="!doc.items.length" class="rounded-xl border border-dashed px-4 py-6 text-center text-[12px]" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-tertiary)' }">
            No line items yet. Click <strong>Seed line items from scope</strong> or <strong>+ Add line</strong>.
          </div>
          <div v-for="(it, i) in doc.items" :key="i" class="rounded-xl border p-3 mb-2 space-y-2" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
            <input v-model="it.title" type="text" placeholder="Title" class="contact-input w-full text-[13px]" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" />
            <input v-model="it.desc" type="text" placeholder="Description (optional)" class="contact-input w-full text-[12px]" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)', background: 'var(--color-bg-elevated)' }" />
            <div class="flex flex-wrap items-end gap-x-2 gap-y-3">
              <div class="w-16">
                <span class="line-label">Qty</span>
                <input v-model.number="it.qty" type="number" min="0" step="0.5" class="contact-input w-full text-[13px] text-center" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" />
              </div>
              <div class="flex-1 min-w-20">
                <span class="line-label">Unit</span>
                <input v-model="it.unit" type="text" placeholder="project, page, hr…" class="contact-input w-full text-[13px]" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" />
              </div>
              <div class="w-32">
                <span class="line-label">Rate</span>
                <div class="relative">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[12px] pointer-events-none" style="color: var(--color-text-tertiary);">RM</span>
                  <input v-model.number="it.rate" type="number" min="0" step="50" class="contact-input w-full text-[13px] pl-9 text-right" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" />
                </div>
              </div>
            </div>
            <!-- Footer: delete on the left, line total on the right -->
            <div class="flex items-center justify-between gap-3 pt-2.5 mt-0.5 border-t" :style="{ borderColor: 'var(--color-border)' }">
              <button type="button" class="inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg text-[12px] font-medium transition-colors hover:bg-(--color-bg-secondary)" :style="{ color: 'var(--color-danger)' }" aria-label="Remove line" @click="removeItem(i)">
                <UIcon name="i-lucide-trash-2" class="size-4" />
                Remove
              </button>
              <div class="flex items-baseline gap-2">
                <span class="text-[12px]" style="color: var(--color-text-tertiary);">Total</span>
                <span class="text-[14px] font-semibold tabular-nums" style="color: var(--color-text);">RM {{ ((Number(it.qty) || 0) * (Number(it.rate) || 0)).toLocaleString() }}</span>
              </div>
            </div>
          </div>
          <div v-if="doc.items.length" class="flex justify-end items-center gap-3 pt-1">
            <span class="text-[12px]" style="color: var(--color-text-tertiary);">Document total</span>
            <span class="text-[16px] font-bold tabular-nums" style="color: var(--color-text);">RM {{ grandTotal.toLocaleString() }}</span>
          </div>
        </div>

        <div class="grid sm:grid-cols-[1fr_auto] gap-4">
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Terms (one per line)</label>
            <textarea v-model="doc.termsText" rows="4" class="contact-input resize-none w-full text-[12px]" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Deposit %</label>
            <input v-model.number="doc.deposit_pct" type="number" min="0" max="100" class="contact-input w-24" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
          </div>
        </div>
      </section>
    </div>

    <!-- Sidebar -->
    <div class="lg:sticky lg:top-20 space-y-4">
      <div class="rounded-2xl border p-5" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Estimate (guide)</p>
        <div v-if="estimate">
          <p class="text-[26px] font-bold tracking-tight leading-none mb-1" style="color: var(--color-text);">
            {{ fmtMyr(estimate.minMyr) }} <span style="color: var(--color-text-tertiary);">–</span> {{ fmtMyr(estimate.maxMyr) }}
          </p>
          <p class="text-[12px]" style="color: var(--color-text-secondary);">{{ formatEta(estimate.etaValue, estimate.etaUnit) }} · engine estimate</p>
        </div>
        <p v-else class="text-[13px]" style="color: var(--color-text-secondary);">Pick a package to see the engine estimate.</p>
        <div class="mt-4 pt-4 border-t flex items-center justify-between" style="border-color: var(--color-border);">
          <span class="text-[12px]" style="color: var(--color-text-tertiary);">Document total</span>
          <span class="text-[16px] font-bold tabular-nums" style="color: var(--color-text);">RM {{ grandTotal.toLocaleString() }}</span>
        </div>
      </div>

      <div class="rounded-2xl border p-5 space-y-3" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <button type="button" class="btn-pill btn-pill-accent w-full justify-center text-[13px]" :disabled="!canSave || saving" @click="save">
          {{ saving ? 'Saving…' : isEdit ? 'Save changes' : 'Save draft' }}
        </button>

        <template v-if="isEdit">
          <button type="button" class="btn-pill btn-pill-ghost w-full justify-center text-[13px]" :disabled="!quotation?.public_token" @click="viewPdf">
            View PDF
          </button>
          <button type="button" class="btn-pill btn-pill-primary w-full justify-center text-[13px]" :disabled="sending || saving" @click="sendToClient">
            {{ sending ? 'Sending…' : 'Send to client' }}
          </button>
          <button type="button" class="btn-pill btn-pill-ghost w-full justify-center text-[13px]" :disabled="accepting" @click="accept">
            {{ accepting ? 'Accepting…' : 'Accept & create order' }}
          </button>
        </template>
      </div>

      <p v-if="error" class="text-[12px] text-center px-3" style="color: var(--color-danger);">{{ error }}</p>
      <p v-else-if="!isEdit" class="text-[11px] text-center px-3" style="color: var(--color-text-tertiary);">Save the draft to enable PDF preview and sending.</p>
    </div>
  </div>
</template>

<style scoped>
/* Micro-label above the qty / unit / rate / total fields in a line item. */
.line-label {
  display: block;
  margin-bottom: 4px;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-tertiary);
}
</style>
