<script setup lang="ts">
import QuoteScopeFields from '~/components/shared/QuoteScopeFields.vue'
import DetailedProposalFields from '~/components/admin/DetailedProposalFields.vue'
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

// ── Detailed proposal (optional inline upgrade) ──────────────────────────────
// When on, the quote saves as layout:'detailed' — the line items become the
// "Scope of work" section and the extra blocks (What's included / option cards /
// care plan) from the child are merged in. Removing it keeps the quote standard.
const detailed = ref(false)
const detailedInitial = ref<Record<string, any> | null>(null)
const detailedRef = ref<InstanceType<typeof DetailedProposalFields> | null>(null)
// Bumped on revert to force the detailed child to remount + re-hydrate from the
// restored payload (it reads its initial state once, on mount).
const detailedKey = ref(0)
function enableDetailed() { detailed.value = true }
function disableDetailed() { detailed.value = false; detailedInitial.value = null }

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

// Hydrate the whole form from a saved quotation — used on mount and on revert.
function loadFromQuotation(q: QuotationLike) {
  client.mode = q.client_id ? 'search' : 'new'
  client.client_id = q.client_id
  client.name = q.name
  client.email = q.email
  client.phone = q.phone ?? ''
  client.company = q.company ?? ''
  hydrateScope(q.form_payload ?? {}, q.package_key)
  const d = q.document ?? {}
  if (d.layout === 'detailed' && d.payload) {
    // Detailed quote: flatten the scope sections back into editable line items
    // (one line per row — section grouping isn't represented in the merged
    // builder) and hand the extra blocks to the child via `detailedInitial`.
    const p = d.payload
    doc.project = p.project ?? ''
    doc.intro = p.intro ?? ''
    const items: LineItem[] = []
    for (const s of (p.sections ?? [])) {
      for (const r of (s.rows ?? [])) {
        items.push({ title: r.title ?? '', desc: r.detail ?? '', qty: 1, unit: '', rate: Number(r.price) || 0 })
      }
    }
    doc.items = items
    doc.termsText = (p.paymentTerms?.items ?? defaultTerms).join('\n')
    doc.deposit_pct = d.deposit_pct ?? 50
    detailedInitial.value = p
    detailed.value = true
  }
  else {
    doc.project = d.project ?? ''
    doc.intro = d.intro ?? ''
    doc.items = (d.items ?? []).map((it: any) => ({
      title: it.title ?? '', desc: it.desc ?? '', qty: Number(it.qty ?? 1), unit: it.unit ?? '', rate: Number(it.rate ?? 0),
    }))
    doc.termsText = (d.terms ?? defaultTerms).join('\n')
    doc.deposit_pct = d.deposit_pct ?? 50
    detailedInitial.value = null
    detailed.value = false
  }
}

onMounted(async () => {
  await loadConfig()

  if (props.quotation) {
    loadFromQuotation(props.quotation)
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

  // Wait for the detailed child (if any) to mount + hydrate so its blocks are part
  // of the snapshot — otherwise an existing detailed quote would read as dirty.
  await nextTick()
  // Snapshot the loaded form so the Save button can detect real edits (`dirty`).
  baseline.value = formFingerprint()
})

// ── Actions ─────────────────────────────────────────────────────────────────
const saving = ref(false)
const sending = ref(false)
const accepting = ref(false)
const error = ref('')

// ── Required-field validation ────────────────────────────────────────────────
// Mirrors the server rules (AdminQuotationRequest): a client (an existing one, or
// name + email) is always required; a package is required for standard quotes
// (optional for detailed); every line item needs a title. These messages drive the
// inline errors + red field highlights; validate() also scrolls to the first miss.
const errors = reactive<{ name: string; email: string; client: string; package: string; items: Record<number, string> }>({
  name: '', email: '', client: '', package: '', items: {},
})

function clearErrors() {
  errors.name = ''; errors.email = ''; errors.client = ''; errors.package = ''; errors.items = {}
}

function scrollToField(id: string) {
  nextTick(() => {
    const el = document.getElementById(id)
    if (!el) return
    el.scrollIntoView({ behavior: 'smooth', block: 'center' })
    if (el instanceof HTMLInputElement) el.focus({ preventScroll: true })
  })
}

function validate(): boolean {
  clearErrors()
  let firstId = ''
  if (!client.client_id) {
    if (client.mode === 'search') {
      errors.client = 'Select an existing client, or switch to New to add one.'
      firstId ||= 'qb-client'
    }
    else {
      if (client.name.trim().length < 2) { errors.name = 'Client name is required.'; firstId ||= 'qb-name' }
      if (!client.email.includes('@')) { errors.email = 'A valid email is required.'; firstId ||= 'qb-email' }
    }
  }
  if (!detailed.value && !scope.packageKey) { errors.package = 'Pick a package.'; firstId ||= 'qb-package' }
  doc.items.forEach((it, i) => {
    if (!it.title.trim()) { errors.items[i] = 'Title is required.'; firstId ||= `qb-item-${i}` }
  })
  if (firstId) scrollToField(firstId)
  return !firstId
}

// Reflect Laravel 422 field errors back onto the inputs (best-effort key mapping).
function mapServerErrors(se?: Record<string, string[]>) {
  if (!se) return
  if (se.name) errors.name = se.name[0]
  if (se.email) errors.email = se.email[0]
  if (se.package_key) errors.package = se.package_key[0]
  for (const k of Object.keys(se)) {
    const m = k.match(/^document\.items\.(\d+)\.title$/)
    if (m) errors.items[Number(m[1])] = se[k][0]
  }
}

// Clear a field's error as soon as the user addresses it.
watch(() => [client.name, client.email, client.client_id, client.mode], () => { errors.name = ''; errors.email = ''; errors.client = '' })
watch(() => scope.packageKey, () => { errors.package = '' })
watch(() => doc.items.map(i => i.title), () => { errors.items = {} })

function buildPayload() {
  const terms = doc.termsText.split('\n').map(t => t.trim()).filter(Boolean)
  const base = {
    client_id: client.client_id,
    name: client.name || null,
    email: client.email || null,
    phone: client.phone || null,
    company: client.company || null,
    package_key: scope.packageKey || null,
    modifiers: modifiers.value,
    addon_keys: scope.addonKeys,
    rush: scope.rush,
    form_payload: { ...scopeToPayload(scope), category_key: scope.categoryKey },
    inquiry_id: props.inquiryId ?? null,
  }

  if (detailed.value) {
    // Line items → a single "Scope of work" section; the rest of the proposal
    // (included / options / care / subtitle / attn) comes from the child.
    const scopeTotal = grandTotal.value
    const rows = doc.items.map(i => ({
      title: i.title,
      detail: i.desc || '',
      price: (Number(i.qty) || 0) * (Number(i.rate) || 0),
    }))
    const sections = rows.length
      ? [{ title: 'Scope of work', rows, totalLabel: 'Scope of work total', total: scopeTotal }]
      : []
    const depositPct = Number(doc.deposit_pct) || 0
    const summaryRows: Record<string, any>[] = sections.map(s => ({ label: s.title, price: s.total }))
    summaryRows.push({ label: 'Project total', price: scopeTotal, total: true, red: true })
    const panels: Record<string, any>[] = []
    if (depositPct > 0 && scopeTotal > 0) {
      const dep = Math.round(scopeTotal * depositPct / 100)
      panels.push({ label: `Deposit (${depositPct}%)`, value: dep, note: 'Payable to commence work.' })
      panels.push({ label: 'Balance on completion', value: scopeTotal - dep, accent: true, note: 'Due before handover.' })
    }
    const blocks = detailedRef.value?.buildBlocks() ?? {}
    return {
      ...base,
      document: {
        layout: 'detailed',
        payload: {
          project: doc.project || null,
          intro: doc.intro || null,
          ...blocks,
          sections,
          summary: { rows: summaryRows },
          ...(panels.length ? { panels } : {}),
          ...(terms.length ? { paymentTerms: { items: terms } } : {}),
        },
        deposit_pct: depositPct,
      },
    }
  }

  return {
    ...base,
    document: {
      project: doc.project || null,
      intro: doc.intro || null,
      items: doc.items.map(i => ({ title: i.title, desc: i.desc || null, qty: Number(i.qty) || 0, unit: i.unit || null, rate: Number(i.rate) || 0 })),
      terms,
      deposit_pct: Number(doc.deposit_pct) || 0,
      tax_rate: 0,
    },
  }
}

// Dirty tracking — in edit mode the "Save changes" button only surfaces once the
// loaded draft is actually modified. The fingerprint omits `modifiers` (emitted
// asynchronously by the scope child after mount, and derived from scope anyway)
// so it never trips a false dirty.
const baseline = ref('')
function formFingerprint(): string {
  return JSON.stringify({ ...buildPayload(), modifiers: undefined })
}
const dirty = computed(() => formFingerprint() !== baseline.value)

// Persist without UI feedback — shared by the Save button and the send flow
// so sending doesn't fire two toasts ("saved" then "sent").
async function persist(): Promise<number | null> {
  if (!validate()) { error.value = 'Please complete the required fields highlighted below.'; return null }
  saving.value = true
  error.value = ''
  try {
    const payload = buildPayload()
    const res = isEdit.value
      ? await apiFetch<{ data: any }>(`/api/v1/admin/quotations/${props.quotation!.id}`, { method: 'PUT', body: payload })
      : await apiFetch<{ data: any }>('/api/v1/admin/quotations', { method: 'POST', body: payload })
    const id = res.data.id
    baseline.value = formFingerprint()
    emit('saved', id)
    return id
  }
  catch (e: any) {
    mapServerErrors(e?.data?.errors)
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

const sendMenuOpen = ref(false)

// Deliver the quote: email it to the client, or just generate + open the PDF to
// share manually. Both save first and mark the quote sent.
async function deliver(channel: 'email' | 'download') {
  if (!isEdit.value) return
  sendMenuOpen.value = false
  sending.value = true
  error.value = ''
  try {
    const id = await persist()
    if (!id) { toast.error('Couldn’t send quotation', error.value || 'Save failed.'); return }
    await apiFetch(`/api/v1/admin/quotations/${id}/send`, { method: 'POST', body: { email: channel === 'email' } })
    emit('sent')
    if (channel === 'download') {
      const token = props.quotation?.public_token
      if (token) window.open(`${window.location.origin}/documents/${token}/pdf`, '_blank', 'noopener')
      toast.success('Marked as sent', 'PDF opened — download and share it with the client.')
    }
    else {
      toast.success('Quotation sent', `PDF emailed to ${client.email || 'the client'}.`)
    }
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
  window.open(`${window.location.origin}/documents/${props.quotation.public_token}/pdf`, '_blank', 'noopener')
}

// Discard unsaved edits and restore the last-saved quotation.
async function revert() {
  if (!props.quotation) return
  loadFromQuotation(props.quotation)
  detailedKey.value++
  clearErrors()
  error.value = ''
  await nextTick()
  baseline.value = formFingerprint()
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
            <input id="qb-client" v-model="clientSearch" type="text" placeholder="Search clients by name or email…" class="contact-input w-full"
              :style="{ borderColor: errors.client ? 'var(--color-danger)' : 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
            <p v-if="errors.client" class="text-[11px] mt-1.5" style="color: var(--color-danger);">{{ errors.client }}</p>
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
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Name <span style="color: var(--color-danger);">*</span></label>
            <input id="qb-name" v-model="client.name" type="text" class="contact-input w-full" :style="{ borderColor: errors.name ? 'var(--color-danger)' : 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
            <p v-if="errors.name" class="text-[11px]" style="color: var(--color-danger);">{{ errors.name }}</p>
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Email <span style="color: var(--color-danger);">*</span></label>
            <input id="qb-email" v-model="client.email" type="email" class="contact-input w-full" :style="{ borderColor: errors.email ? 'var(--color-danger)' : 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
            <p v-if="errors.email" class="text-[11px]" style="color: var(--color-danger);">{{ errors.email }}</p>
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
      <section id="qb-package" class="rounded-2xl border p-6" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <p class="text-[11px] font-semibold uppercase tracking-widest mb-5" style="color: var(--color-text-tertiary);">Package &amp; scope</p>
        <QuoteScopeFields :state="scope" :require-package="!detailed" :package-error="errors.package" @update:estimate="estimate = $event" @update:modifiers="modifiers = $event" />
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
            <div>
              <span class="line-label">Title <span style="color: var(--color-danger);">*</span></span>
              <input :id="`qb-item-${i}`" v-model="it.title" type="text" placeholder="Title" class="contact-input w-full text-[13px]" :style="{ borderColor: errors.items[i] ? 'var(--color-danger)' : 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" />
              <p v-if="errors.items[i]" class="text-[11px] mt-1" style="color: var(--color-danger);">{{ errors.items[i] }}</p>
            </div>
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

        <AdminQuoteTermsDeposit v-model:terms="doc.termsText" v-model:depositPct="doc.deposit_pct" />
      </section>

      <!-- Detailed proposal (optional inline upgrade) -->
      <section v-if="!detailed" class="rounded-2xl border border-dashed p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4" :style="{ borderColor: 'var(--color-border)' }">
        <div>
          <p class="text-[13px] font-semibold" style="color: var(--color-text);">Want a richer proposal?</p>
          <p class="text-[12px] mt-0.5 max-w-md" style="color: var(--color-text-secondary);">Add “What's included”, option cards, and a care plan. Your line items become the scope. Remove it anytime to keep the quote standard.</p>
        </div>
        <button type="button" class="btn-pill btn-pill-warning shrink-0 gap-1.5 text-[13px]" @click="enableDetailed">
          Expand to detailed
          <UIcon name="i-lucide-arrow-down" class="size-3.5" />
        </button>
      </section>
      <section v-else class="rounded-2xl border p-6 space-y-6" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">Detailed proposal</p>
            <p class="text-[12px] mt-1 max-w-md" style="color: var(--color-text-secondary);">Optional blocks added to the client PDF. Saving with these makes it a detailed proposal; the line items above are the scope.</p>
          </div>
          <button type="button" class="inline-flex items-center gap-1.5 text-[12px] font-medium shrink-0 transition-opacity hover:opacity-70" :style="{ color: 'var(--color-danger)' }" @click="disableDetailed">
            <UIcon name="i-lucide-trash-2" class="size-3.5" /> Remove (keep standard)
          </button>
        </div>
        <DetailedProposalFields ref="detailedRef" :key="detailedKey" :initial="detailedInitial" />
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
        <button v-if="!isEdit" type="button" class="btn-pill btn-pill-accent w-full justify-center text-[13px]" :disabled="saving" @click="save">
          {{ saving ? 'Saving…' : 'Save draft' }}
        </button>

        <template v-if="isEdit">
          <!-- Unsaved edits → revert (red) sits where the save button used to be. -->
          <button v-if="dirty" type="button" class="btn-pill btn-pill-danger w-full justify-center gap-1.5 text-[13px]" :disabled="saving" @click="revert">
            <UIcon name="i-lucide-undo-2" class="size-3.5" /> Revert changes
          </button>

          <button v-if="!dirty" type="button" class="btn-pill btn-pill-ghost w-full justify-center text-[13px]" :disabled="!quotation?.public_token" @click="viewPdf">
            View PDF
          </button>
          <div v-if="!dirty" class="relative">
            <button type="button" class="btn-pill btn-pill-primary w-full justify-center text-[13px]" :disabled="sending || saving" @click="sendMenuOpen = !sendMenuOpen">
              {{ sending ? 'Sending…' : 'Send to client' }}
            </button>
            <div v-if="sendMenuOpen" class="fixed inset-0 z-10" @click="sendMenuOpen = false" />
            <div v-if="sendMenuOpen" class="absolute left-0 right-0 mt-2 z-20 rounded-xl border p-1.5 space-y-1"
              :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-card-hover)' }">
              <button type="button" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-[13px] transition-colors hover:bg-(--color-bg-secondary)" style="color: var(--color-text);" @click="deliver('email')">
                <UIcon name="i-lucide-mail" class="size-4" /> Email to client
              </button>
              <button type="button" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-[13px] transition-colors hover:bg-(--color-bg-secondary)" style="color: var(--color-text);" @click="deliver('download')">
                <UIcon name="i-lucide-download" class="size-4" /> Download PDF
              </button>
            </div>
          </div>
          <!-- Save replaces Proceed while there are unsaved edits — you must save first. -->
          <button v-if="dirty" type="button" class="btn-pill btn-pill-accent w-full justify-center text-[13px]" :disabled="saving" @click="save">
            {{ saving ? 'Saving…' : 'Save changes' }}
          </button>
          <button v-else type="button" class="btn-pill btn-pill-accent w-full justify-center text-[13px]" :disabled="accepting" @click="accept">
            {{ accepting ? 'Creating order…' : 'Proceed & Create Order' }}
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
