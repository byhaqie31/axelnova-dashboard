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
const { config, loadConfig } = usePricingEngine()
const toast = useAdminToast()

const isEdit = computed(() => !!props.quotation)

// ── Client (same pattern as QuotationBuilder) ────────────────────────────────
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

let clientTimer: ReturnType<typeof setTimeout>
watch(clientSearch, (q) => {
  clearTimeout(clientTimer)
  if (!q || q.length < 2) { clientResults.value = []; return }
  clientTimer = setTimeout(async () => {
    try {
      const res = await apiFetch<{ data: typeof clientResults.value }>(`/api/v1/admin/clients?search=${encodeURIComponent(q)}`)
      clientResults.value = res.data
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

// ── Pricing basis (internal estimate) ────────────────────────────────────────
const scope = reactive<QuoteScopeState>(defaultQuoteScope())
const estimate = ref<EstimateResult | null>(null)
const modifiers = ref<Record<string, boolean | number>>({})

// ── Detailed document composer ───────────────────────────────────────────────
interface SecRow { title: string; detail: string; price: number | null; priceText: string }
interface Sec { title: string; note: string; rows: SecRow[] }
interface IncGroup { eyebrow: string; itemsText: string; columns: 1 | 2; note: string }
interface OptCard { badge: string; accent: boolean; title: string; sub: string; price: number | null; priceWas: number | null; priceNote: string }
interface CareRow { label: string; detail: string; price: number | null; period: string }

const doc = reactive({
  project: '',
  subtitle: 'Website quotation',
  intro: '',
  attn: '',
  address: '',
  sections: [] as Sec[],
  included: [] as IncGroup[],
  optTitle: 'Package options',
  optPromo: '',
  options: [] as OptCard[],
  careTitle: 'Care & support',
  careNote: '',
  care: [] as CareRow[],
  termsText: '',
  depositPct: 50,
})

const defaultTerms = [
  '50% deposit to commence; balance due on delivery before handover.',
  'Revisions are included as scoped per phase; further rounds are quoted separately.',
  'Third-party costs (domains, fonts, hosting) are billed at cost where applicable.',
]

// ── Section / row helpers ────────────────────────────────────────────────────
function sectionTotal(s: Sec): number {
  return s.rows.reduce((sum, r) => sum + (r.priceText.trim() ? 0 : (Number(r.price) || 0)), 0)
}
const projectTotal = computed(() => doc.sections.reduce((sum, s) => sum + sectionTotal(s), 0))
const depositValue = computed(() => Math.round(projectTotal.value * (Number(doc.depositPct) || 0) / 100))
const balanceValue = computed(() => projectTotal.value - depositValue.value)

function addSection() {
  doc.sections.push({ title: '', note: '', rows: [{ title: '', detail: '', price: null, priceText: '' }] })
}
function removeSection(i: number) { doc.sections.splice(i, 1) }
function addRow(s: Sec) { s.rows.push({ title: '', detail: '', price: null, priceText: '' }) }
function removeRow(s: Sec, i: number) { s.rows.splice(i, 1) }

function addIncluded() { doc.included.push({ eyebrow: '', itemsText: '', columns: 1, note: '' }) }
function removeIncluded(i: number) { doc.included.splice(i, 1) }

function addOption() {
  const letter = String.fromCharCode(65 + doc.options.length)
  doc.options.push({ badge: `OPTION ${letter}`, accent: doc.options.length === 0, title: '', sub: '', price: null, priceWas: null, priceNote: 'one-time' })
}
function removeOption(i: number) { doc.options.splice(i, 1) }

function addCare() { doc.care.push({ label: '', detail: '', price: null, period: 'month' }) }
function removeCare(i: number) { doc.care.splice(i, 1) }

// Seed a first scope section from the chosen package + add-ons.
function seedFromScope() {
  if (!config.value || !scope.packageKey) return
  const rows: SecRow[] = []
  const base = config.value.base_packages[scope.packageKey]
  if (base) {
    const meta = packageMeta(scope.packageKey)
    rows.push({ title: meta.name, detail: meta.tagline, price: base.max, priceText: '' })
  }
  for (const k of scope.addonKeys) {
    const a = config.value.addons[k]
    if (a) rows.push({ title: a.label, detail: '', price: a.amount, priceText: '' })
  }
  if (!rows.length) return
  doc.sections.unshift({ title: 'Scope of work', note: '', rows })
}
function packageMeta(key: string): { name: string; tagline: string } {
  for (const c of config.value?.categories ?? []) {
    const p = c.packages.find(p => p.key === key)
    if (p) return { name: p.name, tagline: p.tagline }
  }
  return { name: key, tagline: '' }
}

// ── Hydrate (edit / inquiry prefill) ──────────────────────────────────────────
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

function hydrateDoc(payload: Record<string, any>) {
  doc.project = payload.project ?? ''
  doc.subtitle = payload.subtitle ?? ''
  doc.intro = payload.intro ?? ''
  doc.attn = payload.client?.attn ?? ''
  doc.address = payload.client?.address ?? ''
  doc.sections = (payload.sections ?? []).map((s: any) => ({
    title: s.title ?? '',
    note: s.note ?? '',
    rows: (s.rows ?? []).map((r: any) => ({
      title: r.title ?? '',
      detail: r.detail ?? '',
      price: r.price ?? null,
      priceText: r.priceText ?? '',
    })),
  }))
  doc.included = (payload.included ?? []).map((g: any) => ({
    eyebrow: g.eyebrow ?? '',
    itemsText: (g.items ?? []).join('\n'),
    columns: g.columns === 2 ? 2 : 1,
    note: g.note ?? '',
  }))
  doc.optTitle = payload.options?.title ?? 'Package options'
  doc.optPromo = payload.options?.promo ?? ''
  doc.options = (payload.options?.cards ?? []).map((c: any) => ({
    badge: c.badge ?? 'OPTION',
    accent: !!c.accent,
    title: c.title ?? '',
    sub: c.sub ?? '',
    price: c.price ?? null,
    priceWas: c.priceWas ?? null,
    priceNote: c.priceNote ?? '',
  }))
  doc.careTitle = payload.care?.title ?? 'Care & support'
  doc.careNote = payload.care?.note ?? ''
  doc.care = (payload.care?.rows ?? []).map((r: any) => ({
    label: r.label ?? '',
    detail: r.detail ?? '',
    price: r.price ?? null,
    period: r.period ?? '',
  }))
  doc.termsText = (payload.paymentTerms?.items ?? []).join('\n')
  // depositPct isn't stored on payload; infer from panels if present.
  const dp = (payload.panels ?? []).find((p: any) => /deposit/i.test(p.label ?? ''))
  if (dp) {
    const m = String(dp.label).match(/(\d+)%/)
    if (m) doc.depositPct = Number(m[1])
  }
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
    if (d.payload) hydrateDoc(d.payload)
    if (!doc.termsText) doc.termsText = defaultTerms.join('\n')
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

// ── Build + actions ──────────────────────────────────────────────────────────
const saving = ref(false)
const sending = ref(false)
const accepting = ref(false)
const error = ref('')

const clientValid = computed(() => !!client.client_id || (client.name.trim().length >= 2 && client.email.includes('@')))
const canSave = computed(() => clientValid.value && !!scope.packageKey)

function buildDocPayload() {
  const sections = doc.sections
    .filter(s => s.title.trim())
    .map(s => ({
      title: s.title,
      rows: s.rows.filter(r => r.title.trim()).map(r => ({
        title: r.title,
        detail: r.detail || '',
        ...(r.priceText.trim() ? { priceText: r.priceText } : { price: Number(r.price) || 0 }),
      })),
      totalLabel: `${s.title} total`,
      total: sectionTotal(s),
      ...(s.note.trim() ? { note: s.note } : {}),
    }))

  const included = doc.included
    .filter(g => g.itemsText.trim())
    .map(g => ({
      ...(g.eyebrow.trim() ? { eyebrow: g.eyebrow } : {}),
      items: g.itemsText.split('\n').map(x => x.trim()).filter(Boolean),
      columns: g.columns,
      ...(g.note.trim() ? { note: g.note } : {}),
    }))

  const cards = doc.options
    .filter(c => c.title.trim())
    .map(c => ({
      badge: c.badge || 'OPTION',
      ...(c.accent ? { accent: true } : {}),
      title: c.title,
      ...(c.sub.trim() ? { sub: c.sub } : {}),
      price: Number(c.price) || 0,
      ...(c.priceWas != null && String(c.priceWas) !== '' ? { priceWas: Number(c.priceWas) } : {}),
      ...(c.priceNote.trim() ? { priceNote: c.priceNote } : {}),
    }))

  const careRows = doc.care
    .filter(r => r.label.trim())
    .map(r => ({
      label: r.label,
      detail: r.detail || '',
      price: Number(r.price) || 0,
      ...(r.period.trim() ? { period: r.period } : {}),
    }))

  const terms = doc.termsText.split('\n').map(t => t.trim()).filter(Boolean)

  const summaryRows: Record<string, any>[] = sections.map(s => ({ label: s.title, price: s.total }))
  summaryRows.push({ label: 'Project total', price: projectTotal.value, total: true, red: true })

  const panels: Record<string, any>[] = []
  if ((Number(doc.depositPct) || 0) > 0 && projectTotal.value > 0) {
    panels.push({ label: `Deposit (${doc.depositPct}%)`, value: depositValue.value, note: 'Payable to commence work.' })
    panels.push({ label: 'Balance on completion', value: balanceValue.value, accent: true, note: 'Due before handover.' })
  }

  return {
    project: doc.project || null,
    subtitle: doc.subtitle || null,
    intro: doc.intro || null,
    ...((doc.attn || doc.address) ? { client: { attn: doc.attn || null, address: doc.address || null } } : {}),
    sections,
    ...(included.length ? { included } : {}),
    ...(cards.length ? { options: { title: doc.optTitle || 'Package options', ...(doc.optPromo.trim() ? { promo: doc.optPromo } : {}), cards } } : {}),
    ...(careRows.length ? { care: { title: doc.careTitle || 'Care & support', rows: careRows, ...(doc.careNote.trim() ? { note: doc.careNote } : {}) } } : {}),
    summary: { rows: summaryRows },
    ...(panels.length ? { panels } : {}),
    ...(terms.length ? { paymentTerms: { items: terms } } : {}),
  }
}

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
      layout: 'detailed',
      payload: buildDocPayload(),
      deposit_pct: Number(doc.depositPct) || 0,
    },
    inquiry_id: props.inquiryId ?? null,
  }
}

async function persist(): Promise<number | null> {
  if (!canSave.value) { error.value = 'Add a client and pick a package (pricing basis) first.'; return null }
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
  if (id) toast.success(isEdit.value ? 'Changes saved' : 'Draft saved', 'Detailed quotation stored. Preview the PDF or send it.')
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
  window.open(`${window.location.origin}/documents/${props.quotation.public_token}/pdf`, '_blank', 'noopener')
}

function fmtRm(n: number) {
  return `RM ${(Number(n) || 0).toLocaleString()}`
}

const fieldStyle = { borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }
const cardStyle = { background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }
</script>

<template>
  <div class="grid lg:grid-cols-[1fr_320px] gap-8 items-start">

    <div class="space-y-8">

      <!-- Client -->
      <section class="rounded-2xl border p-6" :style="cardStyle">
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
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }">
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

        <div v-else class="grid sm:grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Name *</label>
            <input v-model="client.name" type="text" class="contact-input w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }">
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Email *</label>
            <input v-model="client.email" type="email" class="contact-input w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }">
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Phone</label>
            <input v-model="client.phone" type="tel" class="contact-input w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }">
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Company</label>
            <input v-model="client.company" type="text" class="contact-input w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }">
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Attn (optional)</label>
            <input v-model="doc.attn" type="text" placeholder="e.g. Daniel Foong, Marketing Lead" class="contact-input w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }">
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Address (optional)</label>
            <input v-model="doc.address" type="text" class="contact-input w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }">
          </div>
        </div>
      </section>

      <!-- Pricing basis -->
      <section class="rounded-2xl border p-6" :style="cardStyle">
        <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Pricing basis (internal)</p>
        <p class="text-[12px] mb-5" style="color: var(--color-text-tertiary);">Drives the internal estimate &amp; the order value. The client sees the composed document below, not this.</p>
        <QuoteScopeFields :state="scope" @update:estimate="estimate = $event" @update:modifiers="modifiers = $event" />
      </section>

      <!-- Detailed document -->
      <section class="rounded-2xl border p-6 space-y-6" :style="cardStyle">
        <div class="flex items-center justify-between flex-wrap gap-2">
          <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">Detailed document</p>
          <button type="button" class="btn-pill btn-pill-ghost text-[12px]" :disabled="!scope.packageKey" @click="seedFromScope">Seed a scope section</button>
        </div>

        <div class="grid gap-4">
          <div class="grid sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Project title</label>
              <input v-model="doc.project" type="text" placeholder="e.g. Brand website — design & build" class="contact-input w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }">
            </div>
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Subtitle</label>
              <input v-model="doc.subtitle" type="text" placeholder="e.g. Website quotation" class="contact-input w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }">
            </div>
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Intro</label>
            <textarea v-model="doc.intro" rows="2" placeholder="Opening paragraph shown under the title…" class="contact-input resize-none w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
          </div>
        </div>

        <!-- Scope sections -->
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Scope sections</label>
            <button type="button" class="text-[12px]" style="color: var(--color-accent);" @click="addSection">+ Add section</button>
          </div>
          <div v-if="!doc.sections.length" class="rounded-xl border border-dashed px-4 py-6 text-center text-[12px]" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-tertiary)' }">
            No sections yet. <strong>Seed a scope section</strong> from the package, or <strong>+ Add section</strong>.
          </div>

          <div v-for="(s, si) in doc.sections" :key="si" class="rounded-xl border p-3 space-y-3" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
            <div class="flex items-center gap-2">
              <input v-model="s.title" type="text" placeholder="Section title (e.g. Core package: website build)" class="contact-input w-full text-[13px] font-medium" :style="fieldStyle">
              <span class="text-[13px] font-semibold tabular-nums shrink-0 px-1" style="color: var(--color-text);">{{ fmtRm(sectionTotal(s)) }}</span>
            </div>

            <div v-for="(r, ri) in s.rows" :key="ri" class="flex flex-wrap items-end gap-2 rounded-lg border p-2.5" :style="{ borderColor: 'var(--color-border)' }">
              <div class="flex-1 min-w-40 space-y-1.5">
                <input v-model="r.title" type="text" placeholder="Item" class="contact-input w-full text-[13px]" :style="fieldStyle">
                <input v-model="r.detail" type="text" placeholder="Detail (optional)" class="contact-input w-full text-[12px]" :style="fieldStyle">
              </div>
              <div class="w-32">
                <span class="d-label">Price</span>
                <div class="relative">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[12px] pointer-events-none" style="color: var(--color-text-tertiary);">RM</span>
                  <input v-model.number="r.price" type="number" min="0" step="50" :disabled="!!r.priceText.trim()" class="contact-input w-full text-[13px] pl-9 text-right" :style="fieldStyle">
                </div>
              </div>
              <div class="w-28">
                <span class="d-label">Or label</span>
                <input v-model="r.priceText" type="text" placeholder="Free / Incl." class="contact-input w-full text-[12px] text-center" :style="fieldStyle">
              </div>
              <button type="button" class="size-9 rounded-lg flex items-center justify-center shrink-0 transition-colors hover:bg-(--color-bg-secondary)" :style="{ color: 'var(--color-danger)' }" aria-label="Remove row" @click="removeRow(s, ri)">
                <UIcon name="i-lucide-x" class="size-4" />
              </button>
            </div>

            <div class="flex items-center justify-between gap-3 pt-1">
              <button type="button" class="text-[12px]" style="color: var(--color-accent);" @click="addRow(s)">+ Add row</button>
              <button type="button" class="inline-flex items-center gap-1.5 text-[12px] font-medium transition-opacity hover:opacity-70" :style="{ color: 'var(--color-danger)' }" @click="removeSection(si)">
                <UIcon name="i-lucide-trash-2" class="size-3.5" /> Remove section
              </button>
            </div>
            <input v-model="s.note" type="text" placeholder="Section note (optional)" class="contact-input w-full text-[12px]" :style="fieldStyle">
          </div>
        </div>

        <!-- What's included -->
        <div class="space-y-3 pt-2 border-t" :style="{ borderColor: 'var(--color-border)' }">
          <div class="flex items-center justify-between">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">“What's included” groups</label>
            <button type="button" class="text-[12px]" style="color: var(--color-accent);" @click="addIncluded">+ Add group</button>
          </div>
          <div v-for="(g, gi) in doc.included" :key="gi" class="rounded-xl border p-3 space-y-2" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
            <div class="flex items-center gap-2">
              <input v-model="g.eyebrow" type="text" placeholder="Eyebrow (optional, e.g. BASIC SEO)" class="contact-input flex-1 text-[12px]" :style="fieldStyle">
              <select v-model.number="g.columns" class="contact-input text-[12px] w-28" :style="fieldStyle">
                <option :value="1">1 column</option>
                <option :value="2">2 columns</option>
              </select>
              <button type="button" class="size-9 rounded-lg flex items-center justify-center shrink-0 transition-colors hover:bg-(--color-bg-secondary)" :style="{ color: 'var(--color-danger)' }" aria-label="Remove group" @click="removeIncluded(gi)">
                <UIcon name="i-lucide-trash-2" class="size-4" />
              </button>
            </div>
            <textarea v-model="g.itemsText" rows="3" placeholder="One bullet per line…" class="contact-input resize-none w-full text-[12px]" :style="fieldStyle" />
            <input v-model="g.note" type="text" placeholder="Group note (optional)" class="contact-input w-full text-[12px]" :style="fieldStyle">
          </div>
        </div>

        <!-- Options A/B -->
        <div class="space-y-3 pt-2 border-t" :style="{ borderColor: 'var(--color-border)' }">
          <div class="flex items-center justify-between">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Option cards</label>
            <button type="button" class="text-[12px]" style="color: var(--color-accent);" @click="addOption">+ Add option</button>
          </div>
          <div v-if="doc.options.length" class="grid sm:grid-cols-2 gap-2">
            <div class="space-y-1.5">
              <span class="d-label">Options heading</span>
              <input v-model="doc.optTitle" type="text" class="contact-input w-full text-[12px]" :style="fieldStyle">
            </div>
            <div class="space-y-1.5">
              <span class="d-label">Promo pill (optional)</span>
              <input v-model="doc.optPromo" type="text" placeholder="e.g. Launch offer" class="contact-input w-full text-[12px]" :style="fieldStyle">
            </div>
          </div>
          <div v-for="(c, ci) in doc.options" :key="ci" class="rounded-xl border p-3 space-y-2" :style="{ borderColor: c.accent ? 'var(--color-accent)' : 'var(--color-border)', background: 'var(--color-bg)' }">
            <div class="flex items-center gap-2">
              <input v-model="c.badge" type="text" placeholder="OPTION A" class="contact-input w-32 text-[11px] font-semibold uppercase tracking-wider" :style="fieldStyle">
              <input v-model="c.title" type="text" placeholder="Option title" class="contact-input flex-1 text-[13px] font-medium" :style="fieldStyle">
              <button type="button" class="size-9 rounded-lg flex items-center justify-center shrink-0 transition-colors hover:bg-(--color-bg-secondary)" :style="{ color: 'var(--color-danger)' }" aria-label="Remove option" @click="removeOption(ci)">
                <UIcon name="i-lucide-trash-2" class="size-4" />
              </button>
            </div>
            <input v-model="c.sub" type="text" placeholder="Sub line (optional)" class="contact-input w-full text-[12px]" :style="fieldStyle">
            <div class="flex flex-wrap items-end gap-2">
              <div class="w-32">
                <span class="d-label">Price</span>
                <div class="relative">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[12px] pointer-events-none" style="color: var(--color-text-tertiary);">RM</span>
                  <input v-model.number="c.price" type="number" min="0" step="50" class="contact-input w-full text-[13px] pl-9 text-right" :style="fieldStyle">
                </div>
              </div>
              <div class="w-32">
                <span class="d-label">Was (optional)</span>
                <div class="relative">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[12px] pointer-events-none" style="color: var(--color-text-tertiary);">RM</span>
                  <input v-model.number="c.priceWas" type="number" min="0" step="50" class="contact-input w-full text-[13px] pl-9 text-right" :style="fieldStyle">
                </div>
              </div>
              <div class="flex-1 min-w-28">
                <span class="d-label">Price note</span>
                <input v-model="c.priceNote" type="text" placeholder="one-time" class="contact-input w-full text-[12px]" :style="fieldStyle">
              </div>
              <label class="inline-flex items-center gap-1.5 text-[12px] pb-2.5" style="color: var(--color-text-secondary);">
                <input v-model="c.accent" type="checkbox"> Recommended
              </label>
            </div>
          </div>
        </div>

        <!-- Care plan -->
        <div class="space-y-3 pt-2 border-t" :style="{ borderColor: 'var(--color-border)' }">
          <div class="flex items-center justify-between">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Care plan</label>
            <button type="button" class="text-[12px]" style="color: var(--color-accent);" @click="addCare">+ Add plan row</button>
          </div>
          <input v-if="doc.care.length" v-model="doc.careTitle" type="text" placeholder="Care section title" class="contact-input w-full text-[12px]" :style="fieldStyle">
          <div v-for="(r, ri) in doc.care" :key="ri" class="flex flex-wrap items-end gap-2 rounded-xl border p-2.5" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
            <div class="w-36">
              <span class="d-label">Plan</span>
              <input v-model="r.label" type="text" placeholder="Basic" class="contact-input w-full text-[13px]" :style="fieldStyle">
            </div>
            <div class="flex-1 min-w-40">
              <span class="d-label">Detail</span>
              <input v-model="r.detail" type="text" placeholder="Hosting + updates" class="contact-input w-full text-[12px]" :style="fieldStyle">
            </div>
            <div class="w-28">
              <span class="d-label">Price</span>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[12px] pointer-events-none" style="color: var(--color-text-tertiary);">RM</span>
                <input v-model.number="r.price" type="number" min="0" step="10" class="contact-input w-full text-[13px] pl-9 text-right" :style="fieldStyle">
              </div>
            </div>
            <div class="w-24">
              <span class="d-label">Per</span>
              <select v-model="r.period" class="contact-input w-full text-[12px]" :style="fieldStyle">
                <option value="">—</option>
                <option value="month">month</option>
                <option value="year">year</option>
              </select>
            </div>
            <button type="button" class="size-9 rounded-lg flex items-center justify-center shrink-0 transition-colors hover:bg-(--color-bg-secondary)" :style="{ color: 'var(--color-danger)' }" aria-label="Remove plan row" @click="removeCare(ri)">
              <UIcon name="i-lucide-x" class="size-4" />
            </button>
          </div>
          <input v-if="doc.care.length" v-model="doc.careNote" type="text" placeholder="Care note (optional)" class="contact-input w-full text-[12px]" :style="fieldStyle">
        </div>

        <!-- Terms + deposit -->
        <div class="grid sm:grid-cols-[1fr_auto] gap-4 pt-2 border-t" :style="{ borderColor: 'var(--color-border)' }">
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Payment terms (one per line)</label>
            <textarea v-model="doc.termsText" rows="4" class="contact-input resize-none w-full text-[12px]" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Deposit %</label>
            <input v-model.number="doc.depositPct" type="number" min="0" max="100" class="contact-input w-24" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }">
          </div>
        </div>
      </section>
    </div>

    <!-- Sidebar -->
    <div class="lg:sticky lg:top-20 space-y-4">
      <div class="rounded-2xl border p-5" :style="cardStyle">
        <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Document total</p>
        <p class="text-[26px] font-bold tracking-tight leading-none mb-3" style="color: var(--color-text);">{{ fmtRm(projectTotal) }}</p>
        <div class="space-y-1.5 pt-3 border-t" style="border-color: var(--color-border);">
          <div class="flex items-center justify-between text-[12px]">
            <span style="color: var(--color-text-tertiary);">Deposit ({{ doc.depositPct }}%)</span>
            <span class="tabular-nums font-medium" style="color: var(--color-text);">{{ fmtRm(depositValue) }}</span>
          </div>
          <div class="flex items-center justify-between text-[12px]">
            <span style="color: var(--color-text-tertiary);">Balance</span>
            <span class="tabular-nums font-medium" style="color: var(--color-text);">{{ fmtRm(balanceValue) }}</span>
          </div>
        </div>
        <p v-if="estimate" class="text-[11px] mt-3 pt-3 border-t" style="color: var(--color-text-tertiary); border-color: var(--color-border);">
          Engine estimate (internal): {{ fmtRm(estimate.minMyr) }} – {{ fmtRm(estimate.maxMyr) }}
        </p>
      </div>

      <div class="rounded-2xl border p-5 space-y-3" :style="cardStyle">
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
.d-label {
  display: block;
  margin-bottom: 4px;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-tertiary);
}
</style>
