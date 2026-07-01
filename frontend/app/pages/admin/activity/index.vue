<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()

interface Actor { id: number, name: string }
interface ActivityItem {
  id: number
  action: string
  subject_type: string
  subject_id: number
  changes: Record<string, unknown> | null
  actor: Actor | null
  created_at: string
}

const items = ref<ActivityItem[]>([])
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const subjectType = ref('')
const range = ref('')
const loading = ref(true)
const error = ref('')
const collapsed = ref<Set<string>>(new Set())

const subjectOptions = [
  { value: '', label: 'All types' },
  { value: 'Quotation', label: 'Quotations' },
  { value: 'Order', label: 'Orders' },
  { value: 'Inquiry', label: 'Inquiries' },
  { value: 'Referral', label: 'Referrals' },
  { value: 'Payment', label: 'Payments' },
  { value: 'Invoice', label: 'Invoices' },
  { value: 'Project', label: 'Projects' },
]

const rangeOptions = [
  { value: '', label: 'All time' },
  { value: 'today', label: 'Today' },
  { value: '7d', label: 'Last 7 days' },
  { value: '30d', label: 'Last 30 days' },
]

// action → icon + human verb. Unknown actions fall back to the raw key.
const actionMeta: Record<string, { icon: string, label: string }> = {
  'quotation.created': { icon: 'i-lucide-file-plus', label: 'created a quotation' },
  'quotation.updated': { icon: 'i-lucide-file-pen', label: 'edited a quotation' },
  'quotation.status': { icon: 'i-lucide-file-text', label: 'changed quotation status' },
  'quotation.sent': { icon: 'i-lucide-send', label: 'sent a quotation' },
  'quotation.accepted': { icon: 'i-lucide-check-check', label: 'accepted a quotation' },
  'order.created': { icon: 'i-lucide-package-plus', label: 'created an order' },
  'order.status': { icon: 'i-lucide-package-check', label: 'changed order status' },
  'order.schedule': { icon: 'i-lucide-calendar-clock', label: 'updated a schedule' },
  'invoice.issued': { icon: 'i-lucide-receipt-text', label: 'issued an invoice' },
  'payment.recorded': { icon: 'i-lucide-wallet', label: 'recorded a payment' },
  'payment.refunded': { icon: 'i-lucide-rotate-ccw', label: 'refunded a payment' },
  'inquiry.status': { icon: 'i-lucide-inbox', label: 'changed inquiry status' },
  'referral.status': { icon: 'i-lucide-share-2', label: 'changed referral status' },
  'referral.linked_order': { icon: 'i-lucide-link', label: 'linked a referral to an order' },
  'service_category.created': { icon: 'i-lucide-folder-plus', label: 'created a category' },
  'service_category.updated': { icon: 'i-lucide-folder-pen', label: 'updated a category' },
  'service_category.deleted': { icon: 'i-lucide-folder-minus', label: 'deleted a category' },
  'service_package.created': { icon: 'i-lucide-box', label: 'created a package' },
  'service_package.updated': { icon: 'i-lucide-box', label: 'updated a package' },
  'service_package.deleted': { icon: 'i-lucide-box', label: 'deleted a package' },
  'project.created': { icon: 'i-lucide-folder-plus', label: 'created a project' },
  'project.updated': { icon: 'i-lucide-folder-pen', label: 'updated a project' },
  'project.deleted': { icon: 'i-lucide-folder-minus', label: 'deleted a project' },
}

const subjectRoute: Record<string, (id: number) => string> = {
  Quotation: id => `/admin/quotations/${id}`,
  Order: id => `/admin/orders/${id}`,
  Inquiry: id => `/admin/inquiries/${id}`,
  Referral: id => `/admin/referrals/${id}`,
  Payment: id => `/admin/payments/${id}`,
  Invoice: id => `/admin/invoices/${id}`,
  Project: () => `/admin/projects`,
}

function metaFor(action: string) {
  // Unknown (catch-all middleware) actions like 'clients.update' → humanized.
  return actionMeta[action] ?? { icon: 'i-lucide-activity', label: action.replace(/[._]/g, ' ') }
}
function linkFor(item: ActivityItem): string | null {
  return subjectRoute[item.subject_type]?.(item.subject_id) ?? null
}
// Generic catch-all rows carry no real subject — show the request path instead.
function subjectLabel(item: ActivityItem): string {
  if (item.subject_type === 'Request') return String(item.changes?.path ?? 'request')
  return `${item.subject_type} #${item.subject_id}`
}
function changeSummary(changes: Record<string, unknown> | null): string {
  if (!changes) return ''
  if ('from' in changes && 'to' in changes) return `${changes.from} → ${changes.to}`
  if ('method' in changes && 'status' in changes) return `${changes.method} · ${changes.status}`
  return Object.entries(changes).map(([k, v]) => `${k}: ${v}`).join(' · ')
}

// ── Date grouping ──────────────────────────────────────────────────────────────
function dayKey(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
function groupLabel(key: string): string {
  const today = dayKey(new Date())
  const y = new Date()
  y.setDate(y.getDate() - 1)
  if (key === today) return 'Today'
  if (key === dayKey(y)) return 'Yesterday'
  const [yy, mm, dd] = key.split('-').map(Number)
  return new Date(yy!, mm! - 1, dd!).toLocaleDateString('en-MY', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
}
// Newest-first API order preserved; group by local day, in first-seen order.
const groups = computed(() => {
  const order: string[] = []
  const byDay = new Map<string, ActivityItem[]>()
  for (const item of items.value) {
    const key = dayKey(new Date(item.created_at))
    if (!byDay.has(key)) { byDay.set(key, []); order.push(key) }
    byDay.get(key)!.push(item)
  }
  return order.map(key => ({ key, label: groupLabel(key), items: byDay.get(key)! }))
})

// Very recent → relative; older → time of day.
function timeLabel(iso: string): string {
  const d = new Date(iso)
  const mins = Math.floor((Date.now() - d.getTime()) / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins} min ago`
  return d.toLocaleTimeString('en-MY', { hour: 'numeric', minute: '2-digit', hour12: true })
}
function fullTime(iso: string): string {
  return new Date(iso).toLocaleString('en-MY', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function toggleGroup(key: string) {
  const next = new Set(collapsed.value)
  next.has(key) ? next.delete(key) : next.add(key)
  collapsed.value = next
}

function rangeParams(): Record<string, string> {
  if (!range.value) return {}
  const to = new Date()
  const from = new Date()
  if (range.value === '7d') from.setDate(from.getDate() - 6)
  else if (range.value === '30d') from.setDate(from.getDate() - 29)
  return { date_from: dayKey(from), date_to: dayKey(to) }
}

async function fetchActivity() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams({ page: String(page.value), ...rangeParams() })
    if (subjectType.value) params.set('subject_type', subjectType.value)

    const res = await apiFetch<{ data: ActivityItem[], last_page: number, total: number }>(`/api/v1/admin/activity?${params}`)
    items.value = res.data
    lastPage.value = res.last_page
    total.value = res.total
  }
  catch {
    error.value = 'Failed to load activity. Check your session.'
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchActivity)
watch([subjectType, range], () => { page.value = 1; fetchActivity() })
watch(page, fetchActivity)
</script>

<template>
  <div class="max-w-4xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Activity</h1>
      <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">The audit trail — every state change, grouped by day. A system or gateway action shows no actor.</p>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3 mb-6">
      <AdminStatusFilter v-model="subjectType" :options="subjectOptions" label="Type" />
      <AdminStatusFilter v-model="range" :options="rangeOptions" label="Period" :total="total" class="ml-auto" />
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading activity…</div>

    <div v-else-if="!items.length" class="text-center py-16" style="color: var(--color-text-secondary);">
      No activity in this range.
    </div>

    <!-- Date-grouped timeline -->
    <div v-else class="space-y-5">
      <section v-for="group in groups" :key="group.key">
        <!-- Date header (collapse toggle) -->
        <button
          type="button"
          class="w-full flex items-center gap-2.5 mb-3 group"
          :aria-expanded="!collapsed.has(group.key)"
          @click="toggleGroup(group.key)"
        >
          <span
            class="size-6 shrink-0 rounded-full inline-flex items-center justify-center transition-colors"
            :style="{ background: 'var(--color-bg-secondary)', color: 'var(--color-text-tertiary)' }"
          >
            <UIcon
              name="i-lucide-chevron-down"
              class="size-3.5 transition-transform"
              :style="{ transform: collapsed.has(group.key) ? 'rotate(-90deg)' : 'rotate(0deg)' }"
            />
          </span>
          <h2 class="text-[14px] font-semibold tracking-tight" style="color: var(--color-text);">{{ group.label }}</h2>
          <span
            class="ml-auto text-[11px] font-semibold tabular-nums px-2 py-0.5 rounded-full"
            :style="{ background: 'var(--color-bg-secondary)', color: 'var(--color-text-tertiary)' }"
          >{{ group.items.length }}</span>
        </button>

        <!-- Timeline -->
        <div v-show="!collapsed.has(group.key)">
          <div v-for="(item, i) in group.items" :key="item.id" class="flex gap-2.5 sm:gap-4">
            <!-- Time -->
            <time
              class="w-16 sm:w-[76px] shrink-0 text-right text-[11px] sm:text-[12px] pt-3.5 whitespace-nowrap"
              style="color: var(--color-text-tertiary);"
              :title="fullTime(item.created_at)"
            >{{ timeLabel(item.created_at) }}</time>

            <!-- Rail: dot + connecting line -->
            <div class="relative w-3 shrink-0">
              <span
                v-if="i > 0"
                class="absolute left-1/2 -translate-x-1/2 top-0 h-[18px] w-px"
                :style="{ background: 'var(--color-border)' }"
              />
              <span
                v-if="i < group.items.length - 1"
                class="absolute left-1/2 -translate-x-1/2 top-[18px] bottom-0 w-px"
                :style="{ background: 'var(--color-border)' }"
              />
              <span
                class="absolute left-1/2 -translate-x-1/2 top-[13px] size-2.5 rounded-full ring-4 z-10"
                :style="{ background: 'var(--color-accent)', '--tw-ring-color': 'var(--color-bg-secondary)' }"
              />
            </div>

            <!-- Card -->
            <div class="flex-1 min-w-0 pb-3">
              <div
                class="rounded-xl border p-3.5"
                :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
              >
                <div class="flex items-start gap-3">
                  <span
                    class="size-8 shrink-0 rounded-full inline-flex items-center justify-center"
                    :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
                  >
                    <UIcon :name="metaFor(item.action).icon" class="size-4" />
                  </span>
                  <div class="min-w-0 flex-1">
                    <p class="text-[13px] leading-snug flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5" style="color: var(--color-text);">
                      <span class="font-semibold">{{ item.actor?.name ?? 'System' }}</span>
                      <span style="color: var(--color-text-secondary);">{{ metaFor(item.action).label }}</span>
                      <NuxtLink
                        v-if="linkFor(item)"
                        :to="linkFor(item)!"
                        class="font-medium hover:underline"
                        :style="{ color: 'var(--color-accent)' }"
                      >{{ subjectLabel(item) }}</NuxtLink>
                      <span v-else class="font-medium" :style="{ color: 'var(--color-text)' }">{{ subjectLabel(item) }}</span>
                    </p>
                    <p v-if="changeSummary(item.changes)" class="text-[12px] mt-1 tabular-nums" style="color: var(--color-text-tertiary);">
                      {{ changeSummary(item.changes) }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>

    <div v-if="lastPage > 1" class="flex items-center justify-center gap-2 mt-6">
      <button :disabled="page <= 1" class="btn-pill btn-pill-ghost text-[12px]" @click="page--">← Prev</button>
      <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ page }} / {{ lastPage }}</span>
      <button :disabled="page >= lastPage" class="btn-pill btn-pill-ghost text-[12px]" @click="page++">Next →</button>
    </div>
  </div>
</template>
