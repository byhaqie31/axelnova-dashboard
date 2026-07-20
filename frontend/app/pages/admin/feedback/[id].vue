<script setup lang="ts">
import FeedbackScale from '~/components/shared/FeedbackScale.vue'
import ReferenceCode from '~/components/shared/primitives/ReferenceCode.vue'
import StatusPill from '~/components/shared/primitives/StatusPill.vue'
import { feedbackStatuses, npsBuckets } from '~/data/feedbackStatuses'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()
const toast = useAdminToast()
const { confirmOpen, confirmConfig, confirm, resolveConfirm } = useConfirm()

const isNew = computed(() => route.params.id === 'new')

interface FeedbackDetail {
  id: number
  reference_code: string
  order_id: number | null
  order_number: string | null
  name: string | null
  email: string | null
  project_label: string | null
  overall: number | null
  rating_design: number | null
  rating_communication: number | null
  rating_delivery: number | null
  rating_value: number | null
  average_rating: number | null
  nps: number | null
  nps_bucket: 'promoter' | 'passive' | 'detractor' | null
  praise: string | null
  improve: string | null
  publish_consent: boolean
  attribution_name: string | null
  attribution_role: string | null
  status: 'pending' | 'approved' | 'published' | 'archived'
  source: 'self_serve' | 'admin'
  featured: boolean
  sort_order: number
  submitted_at: string | null
  reviewed_at: string | null
  published_at: string | null
}

const record = ref<FeedbackDetail | null>(null)
const loading = ref(!isNew.value)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const message = ref('')

// ── Create form (/new) ─────────────────────────────────────────────────
const mode = ref<'request' | 'log'>('request')
const modeOptions = [
  { value: 'request', label: 'Request from client' },
  { value: 'log', label: 'Log received feedback' },
] as const

const form = reactive({
  order_id: null as number | null,
  name: '',
  email: '',
  project_label: '',
  overall: null as number | null,
  rating_design: null as number | null,
  rating_communication: null as number | null,
  rating_delivery: null as number | null,
  rating_value: null as number | null,
  nps: null as number | null,
  praise: '',
  improve: '',
  publish_consent: false,
  attribution_name: '',
  attribution_role: '',
  featured: false,
  sort_order: 0,
})

const dimensions = [
  { key: 'rating_design', label: 'Design' },
  { key: 'rating_communication', label: 'Communication' },
  { key: 'rating_delivery', label: 'Delivery' },
  { key: 'rating_value', label: 'Value' },
] as const

// ── Order popover dropdown (§12.7) ─────────────────────────────────────
interface OrderOption { id: number, order_number: string, name: string | null }
const orders = ref<OrderOption[]>([])
const orderOpen = ref(false)
const selectedOrder = computed(() => orders.value.find(o => o.id === form.order_id) ?? null)
onKeyStroke('Escape', () => { orderOpen.value = false })

async function fetchOrders() {
  try {
    const res = await apiFetch<{ data: OrderOption[] }>('/api/v1/admin/orders')
    orders.value = res.data
  }
  catch {
    orders.value = []
  }
}

// ── Sort-order pill picker (§12.3) ─────────────────────────────────────
interface Sibling { id: number, sort_order: number }
const siblings = ref<Sibling[]>([])

const occupiedSortOrders = computed(() => {
  const positions = new Set<number>(
    siblings.value
      .filter(s => isNew.value || s.id !== record.value?.id)
      .map(s => s.sort_order),
  )
  if (!isNew.value && record.value) positions.add(record.value.sort_order)
  return [...positions].sort((a, b) => a - b)
})

const nextAvailableSort = computed(() => {
  const others = siblings.value
    .filter(s => isNew.value || s.id !== record.value?.id)
    .map(s => s.sort_order)
  return others.length ? Math.max(...others) + 1 : 0
})

function setSort(n: number) { form.sort_order = n }
function nudgeSort(delta: number) {
  form.sort_order = Math.min(nextAvailableSort.value, Math.max(0, (form.sort_order ?? 0) + delta))
}

async function fetchSiblings() {
  try {
    const res = await apiFetch<{ data: Sibling[] }>('/api/v1/admin/feedback')
    siblings.value = res.data
    if (isNew.value) form.sort_order = nextAvailableSort.value
  }
  catch {
    siblings.value = []
  }
}

// ── Load / save ────────────────────────────────────────────────────────
async function fetchRecord() {
  if (isNew.value) return
  loading.value = true
  try {
    const res = await apiFetch<{ data: FeedbackDetail }>(`/api/v1/admin/feedback/${route.params.id}`)
    record.value = res.data
    Object.assign(form, {
      order_id: res.data.order_id,
      name: res.data.name ?? '',
      email: res.data.email ?? '',
      project_label: res.data.project_label ?? '',
      publish_consent: res.data.publish_consent,
      attribution_name: res.data.attribution_name ?? '',
      attribution_role: res.data.attribution_role ?? '',
      featured: res.data.featured,
      sort_order: res.data.sort_order,
    })
  }
  catch {
    message.value = 'Failed to load feedback.'
  }
  finally {
    loading.value = false
  }
}

async function create() {
  saving.value = true
  errors.value = {}
  message.value = ''
  try {
    const base = {
      mode: mode.value,
      order_id: form.order_id,
      project_label: form.project_label || null,
    }
    const payload = mode.value === 'request'
      ? base
      : {
          ...base,
          name: form.name || null,
          email: form.email || null,
          overall: form.overall,
          rating_design: form.rating_design,
          rating_communication: form.rating_communication,
          rating_delivery: form.rating_delivery,
          rating_value: form.rating_value,
          nps: form.nps,
          praise: form.praise || null,
          improve: form.improve || null,
          publish_consent: form.publish_consent,
          attribution_name: form.attribution_name || null,
          attribution_role: form.attribution_role || null,
          featured: form.featured,
          sort_order: form.sort_order,
        }

    const res = await apiFetch<{ data: FeedbackDetail }>('/api/v1/admin/feedback', { method: 'POST', body: payload })
    toast.success(
      mode.value === 'request' ? 'Feedback requested' : 'Feedback logged',
      mode.value === 'request' ? 'The client will receive the review link by email.' : `${res.data.reference_code} recorded.`,
    )
    await navigateTo(`/admin/feedback/${res.data.id}`)
  }
  catch (e: any) {
    if (e?.data?.errors) errors.value = e.data.errors
    message.value = e?.data?.message ?? 'Failed to save.'
    toast.error('Couldn’t save feedback', message.value)
  }
  finally {
    saving.value = false
  }
}

async function save() {
  if (!record.value) return
  saving.value = true
  errors.value = {}
  message.value = ''
  try {
    const res = await apiFetch<{ data: FeedbackDetail }>(`/api/v1/admin/feedback/${record.value.id}`, {
      method: 'PUT',
      body: {
        project_label: form.project_label || null,
        publish_consent: form.publish_consent,
        attribution_name: form.attribution_name || null,
        attribution_role: form.attribution_role || null,
        featured: form.featured,
        sort_order: form.sort_order,
      },
    })
    record.value = res.data
    toast.success('Feedback saved', `${res.data.reference_code} is up to date.`)
  }
  catch (e: any) {
    if (e?.data?.errors) errors.value = e.data.errors
    message.value = e?.data?.message ?? 'Failed to save.'
    toast.error('Couldn’t save feedback', message.value)
  }
  finally {
    saving.value = false
  }
}

// ── Moderation actions ─────────────────────────────────────────────────
const transitioning = ref(false)

const actions = computed(() => {
  if (!record.value) return []
  return [
    { status: 'approved', label: 'Approve', disabled: record.value.status === 'approved' },
    {
      status: 'published',
      label: 'Publish',
      disabled: record.value.status === 'published' || !form.publish_consent,
      hint: !form.publish_consent ? 'Needs client consent' : undefined,
    },
    { status: 'archived', label: 'Archive', disabled: record.value.status === 'archived' },
  ]
})

async function transition(status: string) {
  if (!record.value || transitioning.value) return
  transitioning.value = true
  try {
    await apiFetch(`/api/v1/admin/feedback/${record.value.id}/status`, { method: 'POST', body: { status } })
    await fetchRecord()
    toast.success('Status updated', `Now ${status}.`)
  }
  catch (e: any) {
    toast.error('Couldn’t update status', e?.data?.message ?? 'Try again.')
  }
  finally {
    transitioning.value = false
  }
}

async function destroy() {
  if (!record.value) return
  const ok = await confirm({
    title: 'Delete this feedback?',
    message: `${record.value.reference_code} will be removed from the module (soft delete). The public link stops working.`,
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (!ok) return
  try {
    await apiFetch(`/api/v1/admin/feedback/${record.value.id}`, { method: 'DELETE' })
    toast.success('Feedback deleted', `${record.value.reference_code} removed.`)
    await navigateTo('/admin/feedback')
  }
  catch (e: any) {
    toast.error('Couldn’t delete', e?.data?.message ?? 'Try again.')
  }
}

function fmtDate(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}

const statusMeta = computed(() =>
  feedbackStatuses.find(s => s.value === record.value?.status) ?? null)

onMounted(() => {
  fetchRecord()
  fetchOrders()
  fetchSiblings()
})
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <NuxtLink
      to="/admin/feedback" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
      style="color: var(--color-text-secondary);"
    >
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All feedback
    </NuxtLink>

    <!-- §12.1 header — no eyebrow -->
    <div class="flex items-start justify-between gap-3 flex-wrap mb-6">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">
          {{ isNew ? 'New feedback' : 'Review feedback' }}
        </h1>
        <div v-if="record" class="flex items-center gap-2.5 mt-2">
          <ReferenceCode :code="record.reference_code" />
          <StatusPill :status="record.status" type="feedback" />
          <span v-if="record.featured" class="text-[11px] font-semibold" :style="{ color: 'var(--color-warning)' }">★ Featured</span>
        </div>
      </div>
      <button
        v-if="record"
        type="button"
        class="btn-table-action is-danger"
        @click="destroy"
      >
        <UIcon name="i-lucide-trash-2" class="size-3.5" /> Delete
      </button>
    </div>

    <p v-if="message" class="mb-4 text-[13px]" :style="{ color: 'var(--color-danger)' }">{{ message }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>

    <!-- ════════ CREATE (/new) ════════ -->
    <form
      v-else-if="isNew" class="rounded-2xl border p-6 space-y-6"
      :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
      @submit.prevent="create"
    >
      <!-- Mode — §12.6 pill toggle -->
      <div>
        <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Mode *</label>
        <div class="flex flex-wrap gap-1.5">
          <button
            v-for="m in modeOptions" :key="m.value" type="button"
            class="standard-pill"
            :style="mode === m.value
              ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
              : { borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }"
            @click="mode = m.value"
          >
            {{ m.label }}
          </button>
        </div>
        <p class="mt-1.5 text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">
          {{ mode === 'request'
            ? 'Emails the client a private review link tied to their order.'
            : 'Record feedback you already received (call, WhatsApp, email).' }}
        </p>
      </div>

      <!-- Order — §12.7 popover dropdown -->
      <div>
        <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">
          Order {{ mode === 'request' ? '*' : '(optional)' }}
        </label>
        <div class="relative">
          <button
            type="button"
            class="standard-select-trigger"
            :aria-expanded="orderOpen"
            @click="orderOpen = !orderOpen"
          >
            <UIcon name="i-lucide-package-check" class="size-4 shrink-0" :style="{ color: 'var(--color-accent)' }" />
            <span class="flex-1 truncate" :style="{ color: selectedOrder ? 'var(--color-text)' : 'var(--color-text-tertiary)' }">
              {{ selectedOrder ? `${selectedOrder.order_number} — ${selectedOrder.name ?? 'Unnamed client'}` : '— pick an order —' }}
            </span>
            <UIcon
              name="i-lucide-chevron-down" class="size-4 shrink-0 transition-transform"
              :class="{ 'rotate-180': orderOpen }"
              :style="{ color: 'var(--color-text-tertiary)' }"
            />
          </button>
          <div v-if="orderOpen" class="fixed inset-0 z-40 cursor-default" @click="orderOpen = false" />
          <Transition name="dropdown-panel">
            <ul v-if="orderOpen" class="standard-select-panel" role="listbox">
              <li v-if="mode === 'log'">
                <button
                  type="button" class="standard-select-option"
                  :aria-selected="form.order_id === null"
                  @click="form.order_id = null; orderOpen = false"
                >
                  <span class="flex-1 truncate">No order — standalone feedback</span>
                  <UIcon v-if="form.order_id === null" name="i-lucide-check" class="size-4 shrink-0" />
                </button>
              </li>
              <li v-for="o in orders" :key="o.id">
                <button
                  type="button" class="standard-select-option"
                  :aria-selected="form.order_id === o.id"
                  @click="form.order_id = o.id; orderOpen = false"
                >
                  <span class="font-mono text-[12px] shrink-0">{{ o.order_number }}</span>
                  <span class="flex-1 truncate">{{ o.name ?? 'Unnamed client' }}</span>
                  <UIcon v-if="form.order_id === o.id" name="i-lucide-check" class="size-4 shrink-0" />
                </button>
              </li>
            </ul>
          </Transition>
        </div>
        <p v-if="errors.order_id?.length" class="mt-1 text-[11px]" :style="{ color: 'var(--color-danger)' }">{{ errors.order_id[0] }}</p>
        <p v-else-if="mode === 'request'" class="mt-1 text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">
          The review link goes to this order's client. Name + email are snapshotted automatically.
        </p>
      </div>

      <div>
        <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Project label</label>
        <input
          v-model="form.project_label" type="text" placeholder="e.g. Roofly.my engagement" class="contact-input w-full"
          :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }"
        >
        <p class="mt-1 text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Shown to the client on the form and on the public wall.</p>
      </div>

      <!-- Log-mode fields -->
      <template v-if="mode === 'log'">
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Client name</label>
            <input
              v-model="form.name" type="text" class="contact-input w-full"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }"
            >
          </div>
          <div>
            <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Client email</label>
            <input
              v-model="form.email" type="email" class="contact-input w-full"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }"
            >
            <p v-if="errors.email?.length" class="mt-1 text-[11px]" :style="{ color: 'var(--color-danger)' }">{{ errors.email[0] }}</p>
          </div>
        </div>

        <div>
          <label class="text-[13px] font-medium block mb-2.5" :style="{ color: 'var(--color-text)' }">
            Overall experience <span :style="{ color: 'var(--color-accent)' }">*</span>
          </label>
          <FeedbackScale v-model="form.overall" :max="5" :labels="['Rough', 'Excellent']" />
          <p v-if="errors.overall?.length" class="mt-1.5 text-[11px]" :style="{ color: 'var(--color-danger)' }">{{ errors.overall[0] }}</p>
        </div>

        <div class="grid sm:grid-cols-2 gap-x-6 gap-y-5">
          <div v-for="d in dimensions" :key="d.key">
            <label class="text-[13px] font-medium block mb-2" :style="{ color: 'var(--color-text)' }">{{ d.label }}</label>
            <FeedbackScale v-model="form[d.key]" :max="5" />
          </div>
        </div>

        <div>
          <label class="text-[13px] font-medium block mb-2.5" :style="{ color: 'var(--color-text)' }">NPS (0–10)</label>
          <FeedbackScale v-model="form.nps" :min="0" :max="10" :labels="['Not likely', 'Extremely likely']" />
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">What we got right</label>
            <textarea
              v-model="form.praise" rows="3" maxlength="2000" class="contact-input w-full"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }"
            />
          </div>
          <div>
            <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Where to improve</label>
            <textarea
              v-model="form.improve" rows="3" maxlength="2000" class="contact-input w-full"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }"
            />
          </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Attribution name</label>
            <input
              v-model="form.attribution_name" type="text" placeholder="e.g. Aina R." class="contact-input w-full"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }"
            >
          </div>
          <div>
            <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Attribution role</label>
            <input
              v-model="form.attribution_role" type="text" placeholder="e.g. Founder, Roofly" class="contact-input w-full"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }"
            >
          </div>
        </div>

        <!-- §12.2 toggle row-cards -->
        <div class="space-y-2 pt-1">
          <button
            type="button" class="w-full flex items-center gap-3 rounded-lg border px-4 py-3 transition-all text-left"
            :style="form.publish_consent
              ? { borderColor: 'var(--color-success)', background: 'var(--color-bg-elevated)' }
              : { borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
            @click="form.publish_consent = !form.publish_consent"
          >
            <span
              class="size-9 rounded-lg flex items-center justify-center shrink-0 transition-colors"
              :style="form.publish_consent
                ? { background: 'var(--color-success-soft)', color: 'var(--color-success)' }
                : { background: 'var(--color-bg-elevated)', color: 'var(--color-text-tertiary)' }"
            >
              <UIcon name="i-lucide-quote" class="size-4" />
            </span>
            <span class="flex-1 min-w-0">
              <span class="block text-[13px] font-medium" :style="{ color: form.publish_consent ? 'var(--color-text)' : 'var(--color-text-tertiary)' }">Client consented to publication</span>
              <span class="block text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">They agreed their words + name can appear on the public site</span>
            </span>
            <span
              class="relative inline-block rounded-full transition-colors shrink-0"
              :style="{ background: form.publish_consent ? 'var(--color-success)' : 'var(--color-switch-off-track)', height: '1.25rem', width: '2.25rem' }"
            >
              <span class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all" :style="{ left: form.publish_consent ? '1.125rem' : '0.125rem' }" />
            </span>
          </button>
        </div>
      </template>

      <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="btn-pill btn-pill-accent text-[13px]" :disabled="saving">
          {{ saving ? 'Saving…' : mode === 'request' ? 'Send request' : 'Log feedback' }}
        </button>
        <NuxtLink to="/admin/feedback" class="btn-pill btn-pill-ghost text-[13px]">Cancel</NuxtLink>
      </div>
    </form>

    <!-- ════════ DETAIL ════════ -->
    <template v-else-if="record">
      <!-- Review actions — §12.6 pill group -->
      <div class="mb-6">
        <div class="flex flex-wrap items-center gap-1.5">
          <button
            v-for="a in actions" :key="a.status" type="button"
            class="standard-pill"
            :disabled="a.disabled || transitioning"
            :style="record.status === a.status
              ? { borderColor: statusMeta?.color, background: statusMeta?.bg, color: statusMeta?.color }
              : a.disabled
                ? { borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-tertiary)', opacity: 0.55, cursor: 'not-allowed' }
                : { borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }"
            :title="a.hint"
            @click="transition(a.status)"
          >
            {{ a.label }}<span v-if="a.hint" class="ml-1 text-[10px]">({{ a.hint }})</span>
          </button>
        </div>
        <p class="mt-1.5 text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">
          Nothing auto-publishes — publishing needs the client's consent toggle below.
        </p>
      </div>

      <div class="space-y-6">
        <!-- The review (read-only — scores are the client's record) -->
        <section
          class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
        >
          <div class="flex items-start justify-between gap-3 flex-wrap mb-5">
            <div>
              <p class="text-[15px] font-semibold" :style="{ color: 'var(--color-text)' }">{{ record.name ?? 'Unnamed client' }}</p>
              <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">
                {{ record.email ?? 'no email' }}<template v-if="record.order_number"> · order
                  <NuxtLink :to="`/admin/orders/${record.order_id}`" class="font-mono underline" :style="{ color: 'var(--color-accent)' }">{{ record.order_number }}</NuxtLink>
                </template>
              </p>
            </div>
            <span
              v-if="record.nps_bucket"
              class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded"
              :style="{ background: npsBuckets[record.nps_bucket]?.bg, color: npsBuckets[record.nps_bucket]?.color }"
            >{{ npsBuckets[record.nps_bucket]?.label }} · {{ record.nps }}</span>
          </div>

          <div v-if="record.submitted_at === null" class="rounded-xl border border-dashed p-6 text-center" :style="{ borderColor: 'var(--color-border)' }">
            <UIcon name="i-lucide-hourglass" class="size-6 mb-2 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
            <p class="text-[13px] font-medium" :style="{ color: 'var(--color-text)' }">Awaiting the client's response</p>
            <p class="text-[12px] mt-1" :style="{ color: 'var(--color-text-secondary)' }">The review link was emailed when this request was created.</p>
          </div>

          <template v-else>
            <div class="mb-5">
              <p class="text-[12px] font-medium mb-2" :style="{ color: 'var(--color-text-secondary)' }">Overall</p>
              <FeedbackScale :model-value="record.overall" :max="5" readonly />
            </div>
            <div class="grid sm:grid-cols-2 gap-x-6 gap-y-4 mb-5">
              <div v-for="d in dimensions" :key="d.key">
                <p class="text-[12px] font-medium mb-2" :style="{ color: 'var(--color-text-secondary)' }">{{ d.label }}</p>
                <FeedbackScale :model-value="record[d.key]" :max="5" readonly />
              </div>
            </div>
            <div v-if="record.nps != null" class="mb-5">
              <p class="text-[12px] font-medium mb-2" :style="{ color: 'var(--color-text-secondary)' }">NPS</p>
              <FeedbackScale :model-value="record.nps" :min="0" :max="10" readonly />
            </div>

            <div v-if="record.praise" class="mb-4">
              <p class="text-[11px] font-semibold uppercase tracking-wider mb-1.5" :style="{ color: 'var(--color-text-tertiary)' }">What we got right</p>
              <p class="text-[14px] leading-relaxed" :style="{ color: 'var(--color-text)' }">{{ record.praise }}</p>
            </div>
            <div v-if="record.improve">
              <p class="text-[11px] font-semibold uppercase tracking-wider mb-1.5" :style="{ color: 'var(--color-text-tertiary)' }">Where to improve</p>
              <p class="text-[14px] leading-relaxed" :style="{ color: 'var(--color-text)' }">{{ record.improve }}</p>
            </div>
          </template>

          <div class="flex flex-wrap gap-x-6 gap-y-1 mt-6 pt-4 border-t" :style="{ borderColor: 'var(--color-border)' }">
            <p class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Submitted: {{ fmtDate(record.submitted_at) }}</p>
            <p class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Reviewed: {{ fmtDate(record.reviewed_at) }}</p>
            <p class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Published: {{ fmtDate(record.published_at) }}</p>
            <p class="text-[11px] capitalize" :style="{ color: 'var(--color-text-tertiary)' }">Source: {{ record.source.replace('_', ' ') }}</p>
          </div>
        </section>

        <!-- Moderation form -->
        <form
          class="rounded-2xl border p-6 space-y-5"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
          @submit.prevent="save"
        >
          <div>
            <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Project label</label>
            <input
              v-model="form.project_label" type="text" class="contact-input w-full"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }"
            >
          </div>

          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Attribution name</label>
              <input
                v-model="form.attribution_name" type="text" class="contact-input w-full"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }"
              >
              <p class="mt-1 text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Shown on the public wall.</p>
            </div>
            <div>
              <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Attribution role</label>
              <input
                v-model="form.attribution_role" type="text" class="contact-input w-full"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }"
              >
            </div>
          </div>

          <!-- §12.3 sort-order pill picker -->
          <div>
            <label class="text-[12px] font-medium block mb-2" :style="{ color: 'var(--color-text-secondary)' }">Wall position</label>
            <div class="flex items-center gap-1.5 flex-wrap">
              <button
                type="button" :disabled="(form.sort_order ?? 0) <= 0" class="size-9 rounded-lg border flex items-center justify-center transition-opacity disabled:opacity-30"
                :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)', color: 'var(--color-text-secondary)' }"
                aria-label="Move position left"
                @click="nudgeSort(-1)"
              >
                <UIcon name="i-lucide-chevron-left" class="size-4" />
              </button>

              <button
                v-for="n in occupiedSortOrders" :key="n" type="button" class="size-9 rounded-lg border flex items-center justify-center text-[13px] font-medium tabular-nums transition-colors"
                :style="form.sort_order === n
                  ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent)', color: 'var(--color-on-accent)' }
                  : { borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)', color: 'var(--color-text)' }"
                :aria-label="`Set position ${n}`"
                @click="setSort(n)"
              >
                {{ n }}
              </button>

              <button
                type="button" class="size-9 rounded-lg flex items-center justify-center transition-colors"
                :style="form.sort_order === nextAvailableSort
                  ? { border: '1px solid var(--color-accent)', background: 'var(--color-accent)', color: 'var(--color-on-accent)' }
                  : { border: '1px dashed var(--color-border)', color: 'var(--color-text-tertiary)' }"
                aria-label="Auto-append at end"
                @click="setSort(nextAvailableSort)"
              >
                <UIcon name="i-lucide-plus" class="size-4" />
              </button>

              <button
                type="button" :disabled="(form.sort_order ?? 0) >= nextAvailableSort" class="size-9 rounded-lg border flex items-center justify-center transition-opacity disabled:opacity-30"
                :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)', color: 'var(--color-text-secondary)' }"
                aria-label="Move position right"
                @click="nudgeSort(1)"
              >
                <UIcon name="i-lucide-chevron-right" class="size-4" />
              </button>
            </div>
            <p class="mt-2 text-[11px] leading-tight" :style="{ color: 'var(--color-text-tertiary)' }">
              <code>+</code> auto-appends at position <code>{{ nextAvailableSort }}</code>. Click an existing number to insert there — the colliding row shifts down.
            </p>
          </div>

          <!-- §12.2 toggle row-cards -->
          <div class="space-y-2 pt-1">
            <button
              type="button" class="w-full flex items-center gap-3 rounded-lg border px-4 py-3 transition-all text-left"
              :style="form.featured
                ? { borderColor: 'var(--color-accent)', background: 'var(--color-bg-elevated)' }
                : { borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
              @click="form.featured = !form.featured"
            >
              <span
                class="size-9 rounded-lg flex items-center justify-center shrink-0 transition-colors"
                :style="form.featured
                  ? { background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
                  : { background: 'var(--color-bg-elevated)', color: 'var(--color-text-tertiary)' }"
              >
                <UIcon name="i-lucide-star" class="size-4" />
              </span>
              <span class="flex-1 min-w-0">
                <span class="block text-[13px] font-medium" :style="{ color: form.featured ? 'var(--color-text)' : 'var(--color-text-tertiary)' }">Featured</span>
                <span class="block text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Pinned to the front of the testimonial wall</span>
              </span>
              <span
                class="relative inline-block rounded-full transition-colors shrink-0"
                :style="{ background: form.featured ? 'var(--color-accent)' : 'var(--color-switch-off-track)', height: '1.25rem', width: '2.25rem' }"
              >
                <span class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all" :style="{ left: form.featured ? '1.125rem' : '0.125rem' }" />
              </span>
            </button>

            <button
              type="button" class="w-full flex items-center gap-3 rounded-lg border px-4 py-3 transition-all text-left"
              :style="form.publish_consent
                ? { borderColor: 'var(--color-success)', background: 'var(--color-bg-elevated)' }
                : { borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
              @click="form.publish_consent = !form.publish_consent"
            >
              <span
                class="size-9 rounded-lg flex items-center justify-center shrink-0 transition-colors"
                :style="form.publish_consent
                  ? { background: 'var(--color-success-soft)', color: 'var(--color-success)' }
                  : { background: 'var(--color-bg-elevated)', color: 'var(--color-text-tertiary)' }"
              >
                <UIcon name="i-lucide-quote" class="size-4" />
              </span>
              <span class="flex-1 min-w-0">
                <span class="block text-[13px] font-medium" :style="{ color: form.publish_consent ? 'var(--color-text)' : 'var(--color-text-tertiary)' }">Publish consent</span>
                <span class="block text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">The client agreed their words + name can appear publicly — required before Publish</span>
              </span>
              <span
                class="relative inline-block rounded-full transition-colors shrink-0"
                :style="{ background: form.publish_consent ? 'var(--color-success)' : 'var(--color-switch-off-track)', height: '1.25rem', width: '2.25rem' }"
              >
                <span class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all" :style="{ left: form.publish_consent ? '1.125rem' : '0.125rem' }" />
              </span>
            </button>
          </div>

          <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="btn-pill btn-pill-accent text-[13px]" :disabled="saving">
              {{ saving ? 'Saving…' : 'Save changes' }}
            </button>
          </div>
        </form>
      </div>
    </template>

    <AdminConfirmDialog :open="confirmOpen" :config="confirmConfig" @resolve="resolveConfirm" />
  </div>
</template>
