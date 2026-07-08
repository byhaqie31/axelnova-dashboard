<script setup lang="ts">
import QuoteScopeFields from '~/components/shared/QuoteScopeFields.vue'
import DetailedProposalFields from '~/components/admin/DetailedProposalFields.vue'
import type { QuoteScopeState, NormalizedPackage } from '~/composables/quoteScope'
import type { EstimateResult, EtaUnit } from '~/composables/usePricingEngine'
import { defaultQuoteScope, normalizePackages } from '~/composables/quoteScope'

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
  estimate_min_myr?: string | number | null
  estimate_max_myr?: string | number | null
  estimate_eta_value?: number | null
  estimate_eta_unit?: string | null
  expires_at?: string | null
  form_payload: Record<string, any> | null
  document: Record<string, any> | null
  referral_partner_id?: number | null
  referrer?: { name: string; relationship_tier: string; commission_pct: number } | null
}

const props = defineProps<{
  quotation?: QuotationLike | null
  inquiryId?: number | null
}>()

const emit = defineEmits<{
  saved: [id: number]
  sent: [quotation: Record<string, any>]
  accepted: [orderId: number]
  delete: []
}>()

const { apiFetch } = useAdminAuth()
const { config, loadConfig, invalidateConfig, fmtMyr, formatEta, calculate } = usePricingEngine()
// The builder is the authoring surface — always reflect the current catalog
// (package / add-on prices edited in /admin/services). Drop the session cache in
// setup, before the scope child mounts and refetches, so it's one fresh fetch.
invalidateConfig()
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

// ── Packages + estimate (multi-package) ──────────────────────────────────────
// One QuoteScopeState per package block; rush is a single quote-level flag. Each
// <QuoteScopeFields> mutates its own block in place. The estimate is the sum of the
// per-package engine results (ETA = the longest), computed here from the blocks.
const packages = ref<QuoteScopeState[]>([defaultQuoteScope()])
const rush = ref(false)

function addPackage() {
  packages.value.push(defaultQuoteScope())
}
function removePackage(i: number) {
  packages.value.splice(i, 1)
  if (packages.value.length === 0) packages.value.push(defaultQuoteScope())
}

// Derive a package's category from the loaded catalog — this is what lights up the
// category pill for a connector/legacy draft that never stored a category_key.
function categoryForPackage(key: string): string {
  for (const c of config.value?.categories ?? []) {
    if (c.packages.some(p => p.key === key)) return c.key
  }
  return ''
}

// Per-package live engine estimate (null for a block with no package chosen yet).
const liveEstimates = computed<(EstimateResult | null)[]>(() =>
  packages.value.map(p => (p.packageKey && config.value)
    ? calculate(p.packageKey, p.scopeValues, p.addonKeys, rush.value)
    : null),
)

const etaToDays = (v: number, u: string) => u === 'hour' ? v / 24 : u === 'day' ? v : u === 'month' ? v * 30 : v * 7

// Summed live estimate across all packages; ETA = the longest package (in days).
const liveEstimate = computed<EstimateResult | null>(() => {
  const valid = liveEstimates.value.filter((e): e is EstimateResult => !!e)
  if (!valid.length) return null
  const winner = valid.reduce((a, b) => etaToDays(b.etaValue, b.etaUnit) > etaToDays(a.etaValue, a.etaUnit) ? b : a)
  return {
    minMyr: valid.reduce((s, e) => s + e.minMyr, 0),
    maxMyr: valid.reduce((s, e) => s + e.maxMyr, 0),
    etaValue: winner.etaValue,
    etaUnit: winner.etaUnit as EtaUnit,
    breakdown: [],
  }
})

// The estimate stored on the row (what the backend last computed / seeded).
const storedEstimate = computed<EstimateResult | null>(() => {
  const q = props.quotation
  if (!q || q.estimate_min_myr == null) return null
  return {
    minMyr: Number(q.estimate_min_myr),
    maxMyr: Number(q.estimate_max_myr),
    etaValue: Number(q.estimate_eta_value ?? 0),
    etaUnit: (q.estimate_eta_unit ?? 'week') as EtaUnit,
    breakdown: [],
  }
})

// Locked decision #5: on load, show the STORED estimate; only switch to the live
// recompute once a pricing input actually changes. New quotes always show live.
const baselineReady = ref(false)
const pricingDirty = ref(false)
const pricingKey = computed(() => JSON.stringify({
  p: packages.value.map(p => ({ k: p.packageKey, s: p.scopeValues, a: p.addonKeys })),
  r: rush.value,
}))
watch(pricingKey, () => { if (baselineReady.value) pricingDirty.value = true })

const showLiveEstimate = computed(() => !isEdit.value || pricingDirty.value)
const headlineEstimate = computed<EstimateResult | null>(() =>
  showLiveEstimate.value ? liveEstimate.value : storedEstimate.value)

// Per-package mini-breakdown for the sidebar (label + range per package).
const packageBreakdown = computed(() =>
  packages.value
    .map((p, i) => ({ name: p.packageKey ? packageMeta(p.packageKey).name : '', estimate: liveEstimates.value[i] }))
    .filter((x): x is { name: string; estimate: EstimateResult } => !!x.name && !!x.estimate),
)

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

// Optional custom validity date (YYYY-MM-DD). Blank → send() defaults it to
// valid_for_days after sending.
const validUntil = ref('')

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

// The canonical packages[] the backend prices / seeds from (empty blocks dropped).
function canonicalPackages() {
  return packages.value
    .filter(p => p.packageKey)
    .map(p => ({ package_key: p.packageKey, scope_values: p.scopeValues, addon_keys: p.addonKeys }))
}
const hasPackages = computed(() => packages.value.some(p => p.packageKey))

// ── Custom (non-catalog) quote ───────────────────────────────────────────────
// A quote with no catalog package but real content (bespoke line items or a
// detailed proposal) is "Custom": its scope is those line items, priced by them.
// We show a Custom identity card in place of the empty package picker. A brand-new
// blank quote (no package, no content) still shows the picker.
const isCustom = computed(() => !hasPackages.value && (doc.items.length > 0 || detailed.value))
// Escape hatch: reveal the catalog picker on a custom quote to convert it.
const showCatalogPicker = ref(false)
const showCustomCard = computed(() => isCustom.value && !showCatalogPicker.value)
// Provenance of the loaded draft — drives the "via Axelnova MCP" remark.
const createdViaConnector = computed(() => {
  const q = props.quotation
  const fp = (q?.form_payload ?? {}) as Record<string, any>
  const d = (q?.document ?? {}) as Record<string, any>
  return (fp.source_meta?.created_via ?? fp.created_via ?? d.created_via ?? null) === 'mcp_connector'
})

// JSON of doc.items right after a seed — lets us detect whether the admin has since
// edited the lines (so we don't clobber manual edits when re-seeding).
const seedSnapshot = ref('')
const seeding = ref(false)

// Seed the document's line items from ALL packages via the SHARED backend
// DocumentSeeder (the same service the MCP connector uses) — base at range
// midpoint, modifiers/add-ons at exact amount, a rush line if on. Confirms before
// replacing hand-edited lines; `auto` skips the confirm (a pristine re-sync).
async function seedItems(opts: { auto?: boolean } = {}) {
  const pkgs = canonicalPackages()
  if (!pkgs.length) return
  if (!opts.auto && doc.items.length && JSON.stringify(doc.items) !== seedSnapshot.value) {
    if (!window.confirm('Replace the current line items with freshly seeded ones from the scope?')) return
  }
  seeding.value = true
  try {
    const res = await apiFetch<{ document: { items?: LineItem[] } }>(
      '/api/v1/admin/quotations/seed-document', { method: 'POST', body: { packages: pkgs, rush: rush.value } })
    doc.items = (res.document.items ?? []).map((it: any) => ({
      title: it.title ?? '', desc: it.desc ?? '', qty: Number(it.qty ?? 1), unit: it.unit ?? '', rate: Number(it.rate ?? 0),
    }))
    seedSnapshot.value = JSON.stringify(doc.items)
    if (!opts.auto) toast.success('Seeded from scope', `${doc.items.length} line item${doc.items.length === 1 ? '' : 's'} generated. Adjust before sending.`)
  }
  catch (e: any) {
    if (!opts.auto) toast.error('Couldn’t seed line items', e?.data?.message || 'Failed to seed from scope.')
  }
  finally {
    seeding.value = false
  }
}

// Keep the document in lock-step with the scope WHILE it's still a pristine seed.
// Once the admin hand-edits a line (items ≠ snapshot) we stop, so their work is
// never overwritten; re-clicking "Seed line items from scope" resumes it.
let seedTimer: ReturnType<typeof setTimeout> | undefined
watch(pricingKey, () => {
  if (detailed.value || !seedSnapshot.value) return
  if (JSON.stringify(doc.items) === seedSnapshot.value) {
    clearTimeout(seedTimer)
    seedTimer = setTimeout(() => seedItems({ auto: true }), 400)
  }
})

function addItem() {
  doc.items.push({ title: '', desc: '', qty: 1, unit: '', rate: 0 })
}
function removeItem(i: number) {
  doc.items.splice(i, 1)
}

// ── Hydrate (edit / inquiry prefill) ────────────────────────────────────────
// Turn ANY stored form_payload shape into the multi-package blocks: normalize to
// packages[], then derive each block's category from the catalog (self-heals a
// connector / legacy draft that never stored a category_key). Rush is quote-level.
function hydratePackages(fp: Record<string, any>, packageKey: string | null) {
  const normalized: NormalizedPackage[] = normalizePackages(fp, packageKey)
  const source: NormalizedPackage[] = normalized.length
    ? normalized
    : [{ package_key: '', scope_values: {}, addon_keys: [] }]
  packages.value = source.map(np => ({
    categoryKey: np.package_key ? categoryForPackage(np.package_key) : '',
    packageKey: np.package_key,
    scopeValues: { ...np.scope_values },
    addonKeys: [...np.addon_keys],
  }))
  rush.value = !!fp.rush
}

// Hydrate the whole form from a saved quotation — used on mount and on revert.
function loadFromQuotation(q: QuotationLike) {
  client.mode = q.client_id ? 'search' : 'new'
  client.client_id = q.client_id
  client.name = q.name
  client.email = q.email
  client.phone = q.phone ?? ''
  client.company = q.company ?? ''
  validUntil.value = q.expires_at ? q.expires_at.slice(0, 10) : ''
  hydratePackages(q.form_payload ?? {}, q.package_key)
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

// ── Draft context (connector provenance / assumptions / open questions) ──────
// Read-only surface for a draft's authoring context — chiefly a connector-created
// draft (its "Via connector" badge + the AI's assumptions/open questions/notes).
const draftContext = computed(() => {
  const q = props.quotation
  if (!q) return null
  const d = q.document ?? {}
  const fp = q.form_payload ?? {}
  const createdVia: string | null = fp.source_meta?.created_via ?? fp.created_via ?? d.created_via ?? null
  // Stamped by the MCP connector's update tool (v3) — shows "last edited via connector"
  // alongside the created-via badge, even on a funnel/admin-created draft.
  const lastUpdatedVia: string | null = fp.source_meta?.last_updated_via ?? null
  const lastUpdatedAt: string | null = fp.source_meta?.last_updated_at ?? null
  const assumptions: string[] = Array.isArray(d.assumptions) ? d.assumptions : []
  const openQuestions: string[] = Array.isArray(d.open_questions) ? d.open_questions : []
  const notes: string | null = d.notes ?? null
  const isConnector = createdVia === 'mcp_connector'
  const editedViaConnector = lastUpdatedVia === 'mcp_connector'
  // Render only when there's context worth showing (connector-touched, or notes/assumptions/questions).
  if (!isConnector && !editedViaConnector && !assumptions.length && !openQuestions.length && !notes) return null
  return { createdVia, isConnector, editedViaConnector, lastUpdatedAt, assumptions, openQuestions, notes }
})

function createdViaLabel(v: string | null): string {
  switch (v) {
    case 'mcp_connector': return 'Via connector'
    case 'quote_funnel': return 'Via quote funnel'
    case 'admin': return 'Built in admin'
    default: return 'Draft'
  }
}

// Visual-only pre-send checklist ticks over the open questions (not persisted).
const checkedQuestions = ref<Record<number, boolean>>({})

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
  // Pricing inputs have settled (scope defaults seeded) — from here a change is a
  // real edit, so the estimate panel may switch from the stored value to the live
  // recompute (locked decision #5).
  baselineReady.value = true
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
  if (!detailed.value && !canonicalPackages().length) { errors.package = 'Pick a package.'; firstId ||= 'qb-package' }
  doc.items.forEach((it, i) => {
    if (!it.title.trim()) { errors.items[i] = 'Title is required.'; firstId ||= `qb-item-${i}` }
  })
  if (firstId) scrollToField(firstId)
  return !firstId
}

// Reflect Laravel 422 field errors back onto the inputs (best-effort key mapping).
function mapServerErrors(se?: Record<string, string[]>) {
  if (!se) return
  const name = se.name?.[0]
  const email = se.email?.[0]
  const pkg = se.package_key?.[0]
  if (name) errors.name = name
  if (email) errors.email = email
  if (pkg) errors.package = pkg
  for (const k of Object.keys(se)) {
    const m = k.match(/^document\.items\.(\d+)\.title$/)
    const msg = se[k]?.[0]
    if (m && msg) errors.items[Number(m[1])] = msg
  }
}

// Clear a field's error as soon as the user addresses it.
watch(() => [client.name, client.email, client.client_id, client.mode], () => { errors.name = ''; errors.email = ''; errors.client = '' })
watch(() => packages.value.map(p => p.packageKey).join(','), () => { errors.package = '' })
watch(() => doc.items.map(i => i.title), () => { errors.items = {} })

function buildPayload() {
  const terms = doc.termsText.split('\n').map(t => t.trim()).filter(Boolean)
  const base = {
    client_id: client.client_id,
    name: client.name || null,
    email: client.email || null,
    phone: client.phone || null,
    company: client.company || null,
    // Canonical multi-package shape — the backend resolves service_package_id and
    // stamps the canonical form_payload (packages[]/rush/breakdown/source_meta).
    packages: canonicalPackages(),
    rush: rush.value,
    expires_at: validUntil.value || null,
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
// loaded draft is actually modified. scope_values defaults are seeded after config
// loads, but the baseline is snapshotted post-nextTick (in onMounted) so that
// seeding never trips a false dirty.
const baseline = ref('')
function formFingerprint(): string {
  return JSON.stringify(buildPayload())
}
const dirty = computed(() => formFingerprint() !== baseline.value)

// ── Live document preview ────────────────────────────────────────────────────
// Reuses buildPayload() so the preview is the exact data that would be saved,
// rendered through the real PDF template (no persist).
const previewData = ref<Record<string, any> | null>(null)
const previewLoading = ref(false)
let previewTimer: ReturnType<typeof setTimeout> | undefined

async function fetchPreview() {
  previewLoading.value = true
  try {
    previewData.value = await apiFetch('/api/v1/admin/quotations/preview', { method: 'POST', body: buildPayload() })
  }
  catch {
    // keep last good preview
  }
  finally {
    previewLoading.value = false
  }
}

watch(() => formFingerprint(), () => {
  clearTimeout(previewTimer)
  previewTimer = setTimeout(fetchPreview, 400)
})

onMounted(() => nextTick(fetchPreview))
onBeforeUnmount(() => clearTimeout(previewTimer))

// Persist without UI feedback — shared by the Save button and the send flow
// so sending doesn't fire two toasts ("saved" then "sent"). `silent` skips the
// `saved` emit so the send flow doesn't trigger a parent refetch that would race
// the fresh quotation `sent` delivers.
async function persist(opts: { silent?: boolean } = {}): Promise<number | null> {
  if (!validate()) { error.value = 'Please complete the required fields highlighted.'; return null }
  saving.value = true
  error.value = ''
  try {
    const payload = buildPayload()
    const res = isEdit.value
      ? await apiFetch<{ data: any }>(`/api/v1/admin/quotations/${props.quotation!.id}`, { method: 'PUT', body: payload })
      : await apiFetch<{ data: any }>('/api/v1/admin/quotations', { method: 'POST', body: payload })
    const id = res.data.id
    baseline.value = formFingerprint()
    if (!opts.silent) emit('saved', id)
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

// Confirm-before-act on the consequential actions (send to client / create order)
// so a stray click can't fire them.
const { confirmOpen, confirmConfig, confirm, resolveConfirm } = useConfirm()

// Deliver the quote: email it to the client, or just generate + open the PDF to
// share manually. Both save first and mark the quote sent.
async function deliver(channel: 'email' | 'download') {
  if (!isEdit.value) return
  sendMenuOpen.value = false
  const ok = await confirm(channel === 'email'
    ? {
        title: 'Send this quotation to the client?',
        message: `This emails ${props.quotation?.reference_code ?? 'the quote'} to ${client.email || 'the client'} and marks it sent.`,
        confirmLabel: 'Send to client',
      }
    : {
        title: 'Mark as sent and download?',
        message: 'This marks the quote sent (no email) and opens the PDF for you to share manually.',
        confirmLabel: 'Mark sent & download',
      })
  if (!ok) return
  sending.value = true
  error.value = ''
  try {
    // Silent so the parent doesn't refetch on `saved` — `sent` hands it the fresh
    // quotation directly, switching the page to the sent view with no refresh.
    const id = await persist({ silent: true })
    if (!id) { toast.error('Couldn’t send quotation', error.value || 'Save failed.'); return }
    const res = await apiFetch<{ data: any }>(`/api/v1/admin/quotations/${id}/send`, { method: 'POST', body: { email: channel === 'email' } })
    emit('sent', res.data)
    if (channel === 'download') {
      const token = res.data?.public_token ?? props.quotation?.public_token
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

// Referral-attributed quotes let the founder confirm the commission % on accept —
// defaults to the referrer's tier estimate (or 10 if the nested referrer summary
// isn't loaded), clamped 5–15. Non-referral quotes never show or send this field.
const isReferralAttributed = computed(() => !!props.quotation?.referral_partner_id)
const commissionPct = ref(10)
watch(() => props.quotation?.referral_partner_id, () => {
  commissionPct.value = props.quotation?.referrer?.commission_pct ?? 10
}, { immediate: true })

async function accept() {
  if (!isEdit.value) return
  if (!(await confirm({
    title: 'Create an order from this quote?',
    message: `This accepts ${props.quotation?.reference_code ?? 'the quotation'} and creates a new order — you can’t undo it here.`,
    confirmLabel: 'Proceed & create order',
  }))) return
  accepting.value = true
  error.value = ''
  try {
    const body = isReferralAttributed.value ? { commission_pct: commissionPct.value } : undefined
    const res = await apiFetch<{ order_id: number }>(`/api/v1/admin/quotations/${props.quotation!.id}/accept`, { method: 'POST', body })
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
            <button
type="button" class="standard-pill" :style="client.mode === 'search'
              ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
              : { borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }"
              @click="clearClient">Existing</button>
            <button
type="button" class="standard-pill" :style="client.mode === 'new'
              ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
              : { borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }"
              @click="newClient">New</button>
          </div>
        </div>

        <!-- Existing: search / selected -->
        <div v-if="client.mode === 'search'">
          <div
v-if="client.client_id" class="flex items-center justify-between rounded-xl border px-4 py-3"
            :style="{ borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)' }">
            <div>
              <p class="text-[13px] font-medium" style="color: var(--color-text);">{{ client.name }}</p>
              <p class="text-[12px]" style="color: var(--color-text-secondary);">{{ client.email }}<span v-if="client.company"> · {{ client.company }}</span></p>
            </div>
            <button type="button" class="text-[12px]" style="color: var(--color-accent);" @click="clearClient">Change</button>
          </div>
          <div v-else class="relative">
            <input
id="qb-client" v-model="clientSearch" type="text" placeholder="Search clients by name or email…" class="contact-input w-full"
              :style="{ borderColor: errors.client ? 'var(--color-danger)' : 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" >
            <p v-if="errors.client" class="text-[11px] mt-1.5" style="color: var(--color-danger);">{{ errors.client }}</p>
            <ul
v-if="clientResults.length" class="absolute z-20 left-0 right-0 mt-1.5 rounded-xl border p-1 max-h-60 overflow-y-auto"
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
            <input id="qb-name" v-model="client.name" type="text" class="contact-input w-full" :style="{ borderColor: errors.name ? 'var(--color-danger)' : 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" >
            <p v-if="errors.name" class="text-[11px]" style="color: var(--color-danger);">{{ errors.name }}</p>
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Email <span style="color: var(--color-danger);">*</span></label>
            <input id="qb-email" v-model="client.email" type="email" class="contact-input w-full" :style="{ borderColor: errors.email ? 'var(--color-danger)' : 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" >
            <p v-if="errors.email" class="text-[11px]" style="color: var(--color-danger);">{{ errors.email }}</p>
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Phone</label>
            <input v-model="client.phone" type="tel" class="contact-input w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" >
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Company</label>
            <input v-model="client.company" type="text" class="contact-input w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" >
          </div>
        </div>
      </section>

      <!-- Draft context (connector provenance / assumptions / open questions) -->
      <section v-if="draftContext" class="rounded-2xl border p-6" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <div class="flex items-center justify-between gap-2 mb-4 flex-wrap">
          <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">Draft context</p>
          <div class="flex items-center gap-2 flex-wrap justify-end">
            <span
v-if="draftContext.createdVia"
              class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium"
              :style="draftContext.isConnector
                ? { background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
                : { background: 'var(--color-bg-secondary)', color: 'var(--color-text-secondary)' }">
              <UIcon :name="draftContext.isConnector ? 'i-lucide-bot' : 'i-lucide-pen-line'" class="size-3.5" />
              {{ createdViaLabel(draftContext.createdVia) }}
            </span>
            <span
v-if="draftContext.editedViaConnector"
              class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium"
              :style="{ background: 'var(--color-bg-secondary)', color: 'var(--color-text-secondary)' }"
              :title="draftContext.lastUpdatedAt ? `Last edited via connector on ${new Date(draftContext.lastUpdatedAt).toLocaleString('en-MY')}` : undefined">
              <UIcon name="i-lucide-history" class="size-3.5" />
              Last edited via connector
            </span>
          </div>
        </div>

        <div class="space-y-5">
          <div v-if="draftContext.assumptions.length">
            <p class="text-[12px] font-medium mb-2" style="color: var(--color-text-secondary);">Assumptions</p>
            <ul class="space-y-1.5">
              <li v-for="(a, i) in draftContext.assumptions" :key="i" class="flex gap-2 text-[12px]" style="color: var(--color-text-secondary);">
                <UIcon name="i-lucide-info" class="size-3.5 shrink-0 mt-0.5" style="color: var(--color-text-tertiary);" />
                <span>{{ a }}</span>
              </li>
            </ul>
          </div>

          <div v-if="draftContext.openQuestions.length">
            <p class="text-[12px] font-medium mb-2" style="color: var(--color-text-secondary);">
              Open questions <span class="font-normal" style="color: var(--color-text-tertiary);">— confirm before sending</span>
            </p>
            <ul class="space-y-1.5">
              <li v-for="(q, i) in draftContext.openQuestions" :key="i">
                <label class="flex gap-2.5 items-start cursor-pointer text-[12px]">
                  <input v-model="checkedQuestions[i]" type="checkbox" class="mt-0.5 size-3.5 shrink-0" style="accent-color: var(--color-accent);" >
                  <span :style="{ color: checkedQuestions[i] ? 'var(--color-text-tertiary)' : 'var(--color-text-secondary)', textDecoration: checkedQuestions[i] ? 'line-through' : 'none' }">{{ q }}</span>
                </label>
              </li>
            </ul>
          </div>

          <div v-if="draftContext.notes">
            <p class="text-[12px] font-medium mb-1.5" style="color: var(--color-text-secondary);">Notes</p>
            <p class="text-[12px] whitespace-pre-line" style="color: var(--color-text-secondary);">{{ draftContext.notes }}</p>
          </div>
        </div>
      </section>

      <!-- Packages & scope — repeatable, one block per package (multi-package quote) -->
      <section id="qb-package" class="space-y-4">
        <!-- Custom (non-catalog) quote → an identity card in place of the empty
             picker. Its scope IS the line items below; there are no catalog
             scope-fields/add-ons for custom work. -->
        <div
v-if="showCustomCard" class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <div class="flex items-center justify-between mb-5">
            <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">Custom package</p>
            <span
              class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium"
              :style="createdViaConnector
                ? { background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
                : { background: 'var(--color-bg-secondary)', color: 'var(--color-text-secondary)' }">
              <UIcon :name="createdViaConnector ? 'i-lucide-bot' : 'i-lucide-shapes'" class="size-3.5" />
              {{ createdViaConnector ? 'Custom · via Axelnova MCP' : 'Custom' }}
            </span>
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Custom package name</label>
            <input
v-model="doc.project" type="text" placeholder="e.g. Custom e-commerce build"
              class="contact-input w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" >
            <p class="text-[11px]" style="color: var(--color-text-tertiary);">Outside the catalog — this names the quote (its project title); it’s priced by the line items below.</p>
          </div>
          <button
type="button" class="mt-4 inline-flex items-center gap-1.5 text-[12px] font-medium transition-opacity hover:opacity-70"
            :style="{ color: 'var(--color-accent)' }" @click="showCatalogPicker = true">
            <UIcon name="i-lucide-package" class="size-3.5" /> Use a catalog package instead
          </button>
        </div>

        <template v-else>
          <!-- Reveal the picker on a custom quote for conversion — offer a way back. -->
          <button
v-if="isCustom" type="button" class="inline-flex items-center gap-1.5 text-[12px] font-medium transition-opacity hover:opacity-70"
            :style="{ color: 'var(--color-text-secondary)' }" @click="showCatalogPicker = false">
            <UIcon name="i-lucide-arrow-left" class="size-3.5" /> Back to custom
          </button>

          <div
v-for="(pkg, i) in packages" :key="i" class="rounded-2xl border p-6"
            :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <div class="flex items-center justify-between mb-5">
              <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">
                Package &amp; scope<span v-if="packages.length > 1"> · {{ i + 1 }}</span>
              </p>
              <button
v-if="packages.length > 1" type="button"
                class="inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg text-[12px] font-medium transition-colors hover:bg-(--color-bg-secondary)"
                :style="{ color: 'var(--color-danger)' }" @click="removePackage(i)">
                <UIcon name="i-lucide-trash-2" class="size-3.5" /> Remove
              </button>
            </div>
            <QuoteScopeFields
:state="pkg" :rush="rush" :require-package="!detailed"
              :package-error="i === 0 ? errors.package : ''" />
          </div>

          <button
type="button"
            class="w-full rounded-2xl border border-dashed px-4 py-3.5 text-[13px] font-medium transition-colors hover:bg-(--color-bg-secondary) flex items-center justify-center gap-2"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }"
            @click="addPackage">
            <UIcon name="i-lucide-plus" class="size-4" /> Add another package
          </button>

          <!-- Rush — one quote-level flag applied to every package. -->
          <div class="rounded-2xl border p-5" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="rush" type="checkbox" class="sr-only" >
              <span class="rush-track" :class="{ active: rush }" />
              <span>
                <span class="text-[13px] font-medium" style="color: var(--color-text);">Rush delivery</span>
                <span class="text-[12px] ml-2" style="color: var(--color-text-tertiary);">(+20%, week/month timelines reduced ~30%)</span>
              </span>
            </label>
          </div>
        </template>
      </section>

      <!-- Quotation document -->
      <section class="rounded-2xl border p-6 space-y-6" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <div class="flex items-center justify-between">
          <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">Quotation document</p>
          <button type="button" class="btn-pill btn-pill-ghost text-[12px]" :disabled="!hasPackages || seeding" @click="seedItems()">
            {{ seeding ? 'Seeding…' : 'Seed line items from scope' }}
          </button>
        </div>

        <div class="grid gap-4">
          <!-- Project title lives in the Custom package card when this is a custom
               quote (it names the package there) — hidden here to avoid two inputs
               for the one value. -->
          <div v-if="!showCustomCard" class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Project title</label>
            <input v-model="doc.project" type="text" placeholder="e.g. Brand website — design & front-end build" class="contact-input w-full" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" >
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
              <input :id="`qb-item-${i}`" v-model="it.title" type="text" placeholder="Title" class="contact-input w-full text-[13px]" :style="{ borderColor: errors.items[i] ? 'var(--color-danger)' : 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" >
              <p v-if="errors.items[i]" class="text-[11px] mt-1" style="color: var(--color-danger);">{{ errors.items[i] }}</p>
            </div>
            <input v-model="it.desc" type="text" placeholder="Description (optional)" class="contact-input w-full text-[12px]" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)', background: 'var(--color-bg-elevated)' }" >
            <div class="flex flex-wrap items-end gap-x-2 gap-y-3">
              <div class="w-16">
                <span class="line-label">Qty</span>
                <input v-model.number="it.qty" type="number" min="0" step="0.5" class="contact-input w-full text-[13px] text-center" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" >
              </div>
              <div class="flex-1 min-w-20">
                <span class="line-label">Unit</span>
                <input v-model="it.unit" type="text" placeholder="project, page, hr…" class="contact-input w-full text-[13px]" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" >
              </div>
              <div class="w-32">
                <span class="line-label">Rate</span>
                <div class="relative">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[12px] pointer-events-none" style="color: var(--color-text-tertiary);">RM</span>
                  <input v-model.number="it.rate" type="number" min="0" step="50" class="contact-input w-full text-[13px] pl-9 text-right" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" >
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

        <AdminQuoteTermsDeposit v-model:terms="doc.termsText" v-model:deposit-pct="doc.deposit_pct" />

        <div class="space-y-1.5 pt-2 border-t" :style="{ borderColor: 'var(--color-border)' }">
          <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">
            Valid until <span class="font-normal" style="color: var(--color-text-tertiary);">(optional)</span>
          </label>
          <input v-model="validUntil" type="date" class="contact-input w-full sm:w-56" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" >
          <p class="text-[11px]" style="color: var(--color-text-tertiary);">Leave blank to default to {{ config?.valid_for_days ?? 30 }} days after sending.</p>
        </div>
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
        <div v-if="headlineEstimate">
          <p class="text-[26px] font-bold tracking-tight leading-none mb-1" style="color: var(--color-text);">
            {{ fmtMyr(headlineEstimate.minMyr) }} <span style="color: var(--color-text-tertiary);">–</span> {{ fmtMyr(headlineEstimate.maxMyr) }}
          </p>
          <p class="text-[12px]" style="color: var(--color-text-secondary);">
            {{ formatEta(headlineEstimate.etaValue, headlineEstimate.etaUnit) }} · {{ showLiveEstimate ? 'engine estimate' : 'stored estimate' }}
          </p>

          <!-- Per-package mini-breakdown (label + range), shown with the live recompute. -->
          <div v-if="showLiveEstimate && packageBreakdown.length" class="mt-3 pt-3 border-t space-y-1.5" style="border-color: var(--color-border);">
            <p class="text-[10px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">Per package</p>
            <div v-for="(line, i) in packageBreakdown" :key="i" class="flex items-center justify-between gap-3 text-[12px]">
              <span class="truncate" style="color: var(--color-text-secondary);">{{ line.name }}</span>
              <span class="tabular-nums shrink-0" style="color: var(--color-text);">{{ fmtMyr(line.estimate.minMyr) }}–{{ fmtMyr(line.estimate.maxMyr) }}</span>
            </div>
          </div>
          <p v-else-if="!showLiveEstimate" class="text-[11px] mt-2" style="color: var(--color-text-tertiary);">Edit a package to re-price.</p>
        </div>
        <p v-else class="text-[13px]" style="color: var(--color-text-secondary);">Pick a package to see the engine estimate.</p>
        <div class="mt-4 pt-4 border-t flex items-center justify-between" style="border-color: var(--color-border);">
          <span class="text-[12px]" style="color: var(--color-text-tertiary);">Document total</span>
          <span class="text-[16px] font-bold tabular-nums" style="color: var(--color-text);">RM {{ grandTotal.toLocaleString() }}</span>
        </div>
      </div>

      <div class="rounded-2xl border p-5 space-y-3" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <AdminDocumentPreviewModal :data="previewData" :disabled="!previewData" block />
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
            <div
v-if="sendMenuOpen" class="absolute left-0 right-0 mt-2 z-20 rounded-xl border p-1.5 space-y-1"
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
          <template v-else>
            <div v-if="isReferralAttributed">
              <label for="qb-commission-pct" class="text-[11px] font-medium uppercase tracking-wider mb-1 block" style="color: var(--color-text-tertiary);">
                Commission % <span v-if="props.quotation?.referrer" class="normal-case font-normal" style="color: var(--color-text-secondary);">— {{ props.quotation.referrer.name }}</span>
              </label>
              <input
id="qb-commission-pct" v-model.number="commissionPct" type="number" min="5" max="15" class="contact-input w-full text-[13px]"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" >
            </div>
            <button type="button" class="btn-pill btn-pill-accent w-full justify-center text-[13px]" :disabled="accepting" @click="accept">
              {{ accepting ? 'Creating order…' : 'Proceed & Create Order' }}
            </button>
          </template>

          <!-- Delete — same spot + treatment as a sent quote's Actions panel, so the
               placement is identical across statuses. Separated, danger-text only. -->
          <div class="pt-3 mt-1 border-t" :style="{ borderColor: 'var(--color-border)' }">
            <button type="button" class="btn-pill btn-pill-ghost w-full justify-center text-[13px]" :style="{ color: 'var(--color-danger)' }" @click="emit('delete')">
              <UIcon name="i-lucide-trash-2" class="size-3.5" /> Delete quotation
            </button>
          </div>
        </template>
      </div>

      <p v-if="error" class="text-[12px] text-center px-3" style="color: var(--color-danger);">{{ error }}</p>
      <p v-else-if="!isEdit" class="text-[11px] text-center px-3" style="color: var(--color-text-tertiary);">Save the draft to enable PDF preview and sending.</p>
    </div>

    <!-- Confirm gate for send / create-order. -->
    <AdminConfirmDialog :open="confirmOpen" :config="confirmConfig" @resolve="resolveConfirm" />
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

/* Quote-level rush switch (mirrors the scope-field toggle track). */
.rush-track {
  display: inline-flex;
  width: 36px;
  height: 20px;
  border-radius: 999px;
  background: var(--color-border-strong);
  position: relative;
  flex-shrink: 0;
  transition: background 0.15s ease;
}

.rush-track::after {
  content: '';
  position: absolute;
  top: 2px;
  left: 2px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: white;
  transition: transform 0.15s ease;
}

.rush-track.active {
  background: var(--color-accent);
}

.rush-track.active::after {
  transform: translateX(16px);
}
</style>
