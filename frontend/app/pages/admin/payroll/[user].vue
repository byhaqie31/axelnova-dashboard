<script setup lang="ts">
// One teammate's payroll detail — full payslip history with yearly totals.
// Pick a year: the tiles show that year's paid / pending / allowance / extras,
// and the list is that year's slips month-by-month with Settle. Founder-only.
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

function errMessage(e: unknown): string | undefined {
  return (e as { data?: { message?: string } } | null)?.data?.message
}

interface UserInfo { id: number, name: string, role: string, deactivated: boolean, monthly_allowance_myr: number | null }
interface YearSummary {
  count: number
  gross_total_myr: number
  paid_total_myr: number
  pending_total_myr: number
  allowance_total_myr: number
  extras_total_myr: number
}
interface PayrollEntry {
  id: number
  period_label: string
  allowance_snapshot_myr: number | null
  task_extras_myr: number
  gross_myr: number
  legacy: boolean
  settled: boolean
  paid_at: string | null
  method: string | null
  note: string | null
  tasks: { id: number, title: string, pay_amount_myr: number | null }[]
}
interface Detail {
  user: UserInfo
  years: number[]
  summary_by_year: Record<string, YearSummary>
  entries: PayrollEntry[]
}

const detail = ref<Detail | null>(null)
const loading = ref(true)
const error = ref('')
const selectedYear = ref(new Date().getFullYear())

useHead(() => ({ title: detail.value ? `${detail.value.user.name} — Payroll` : 'Payroll' }))

async function fetchDetail() {
  loading.value = true
  error.value = ''
  try {
    const d = await apiFetch<Detail>(`/api/v1/admin/payroll/user/${route.params.user}`)
    detail.value = d
    const thisYear = new Date().getFullYear()
    selectedYear.value = d.years.includes(thisYear) ? thisYear : (d.years[0] ?? thisYear)
  }
  catch (e: unknown) {
    const status = (e as { status?: number } | null)?.status
    if (status === 403) error.value = 'Founder only.'
    else if (status === 404) error.value = 'Teammate not found.'
    else error.value = 'Failed to load payroll. Check your session.'
  }
  finally {
    loading.value = false
  }
}
onMounted(fetchDetail)

const yearItems = computed(() => (detail.value?.years ?? []).map(y => ({ label: String(y), value: y })))
const yearSummary = computed<YearSummary | null>(() =>
  detail.value?.summary_by_year[String(selectedYear.value)] ?? null)
const entriesForYear = computed(() =>
  (detail.value?.entries ?? []).filter(e => e.period_label.startsWith(String(selectedYear.value))))

const tiles = computed(() => {
  const s = yearSummary.value
  return [
    { key: 'paid', label: 'Paid', value: fmtMyr(s?.paid_total_myr ?? 0), fg: 'var(--color-success)', bg: 'var(--status-succeeded-bg)' },
    { key: 'pending', label: 'Pending', value: fmtMyr(s?.pending_total_myr ?? 0), fg: 'var(--color-warning)', bg: 'var(--status-refunded-bg)' },
    { key: 'allowance', label: 'Allowance', value: fmtMyr(s?.allowance_total_myr ?? 0), fg: 'var(--color-accent)', bg: 'var(--color-accent-soft)' },
    { key: 'extras', label: 'Task extras', value: fmtMyr(s?.extras_total_myr ?? 0), fg: 'var(--color-accent)', bg: 'var(--color-accent-soft)' },
  ]
})

// ── Settle ─────────────────────────────────────────────────────────────────
const methodOptions = [
  { value: 'bank_transfer', label: 'Bank transfer' },
  { value: 'duitnow', label: 'DuitNow' },
  { value: 'cash', label: 'Cash' },
  { value: 'other', label: 'Other' },
]
const pendingSettle = ref<PayrollEntry | null>(null)
const settleMethod = ref('bank_transfer')
const settling = ref(false)

function openSettle(entry: PayrollEntry) {
  pendingSettle.value = entry
  settleMethod.value = entry.method ?? 'bank_transfer'
}
async function confirmSettle() {
  const entry = pendingSettle.value
  if (!entry || settling.value) return
  settling.value = true
  try {
    await apiFetch(`/api/v1/admin/payroll/${entry.id}/settle`, { method: 'POST', body: { method: settleMethod.value } })
    toast.success('Payslip settled', `${entry.period_label} marked paid.`)
    pendingSettle.value = null
    await fetchDetail()
  }
  catch (e) {
    toast.error('Couldn’t settle the payslip', errMessage(e) ?? 'Please try again.')
    pendingSettle.value = null
  }
  finally {
    settling.value = false
  }
}
onKeyStroke('Escape', () => {
  if (pendingSettle.value) pendingSettle.value = null
})

function fmtDate(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}
function fmtMonth(period: string) {
  const [y, m] = period.split('-')
  const d = new Date(Number(y), Number(m) - 1, 1)
  return d.toLocaleDateString('en-MY', { month: 'long', year: 'numeric' })
}
function fmtMyr(amount: number | null) {
  if (amount == null) return '—'
  return `RM ${Number(amount).toLocaleString('en-MY')}`
}
function roleLabel(role: string) {
  return role.charAt(0).toUpperCase() + role.slice(1)
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <NuxtLink to="/admin/payroll" class="inline-flex items-center gap-1.5 text-[13px] mb-6 transition-colors hover:opacity-80" style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All payroll
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading payroll…</div>

    <div v-else-if="error || !detail" class="rounded-2xl border p-12 text-center" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <UIcon name="i-lucide-banknote" class="size-8 mb-3 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
      <p class="text-[14px] font-medium" :style="{ color: 'var(--color-text)' }">{{ error || 'Not found' }}</p>
    </div>

    <template v-else>
      <!-- Header -->
      <div class="flex items-start justify-between gap-4 flex-wrap mb-8">
        <div class="flex items-center gap-3">
          <span class="size-12 rounded-2xl inline-flex items-center justify-center text-[18px] font-bold shrink-0" :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }">
            {{ detail.user.name.charAt(0).toUpperCase() }}
          </span>
          <div>
            <h1 class="text-[24px] font-bold tracking-tight" style="color: var(--color-text);">{{ detail.user.name }}</h1>
            <p class="text-[13px] mt-0.5" style="color: var(--color-text-secondary);">
              {{ roleLabel(detail.user.role) }} · Allowance {{ fmtMyr(detail.user.monthly_allowance_myr) }}
            </p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <NuxtLink :to="`/admin/users/${detail.user.id}`" class="btn-pill btn-pill-ghost text-[13px]">
            <UIcon name="i-lucide-user" class="size-4" /> Profile
          </NuxtLink>
          <label v-if="detail.years.length" class="block">
            <AdminSelect v-model="selectedYear" :items="yearItems" class="w-28" />
          </label>
        </div>
      </div>

      <!-- No history -->
      <div v-if="!detail.years.length" class="rounded-2xl border p-12 text-center" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
        <UIcon name="i-lucide-receipt" class="size-8 mb-3 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
        <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">No payslips yet</p>
        <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">Generate {{ detail.user.name.split(' ')[0] }}'s first payslip from the payroll roster.</p>
      </div>

      <template v-else>
        <!-- Yearly tiles -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-8">
          <div v-for="t in tiles" :key="t.key" class="rounded-2xl border p-4" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <span class="size-9 rounded-xl inline-flex items-center justify-center mb-3" :style="{ background: t.bg, color: t.fg }">
              <UIcon name="i-lucide-banknote" class="size-[18px]" />
            </span>
            <p class="text-[22px] sm:text-[24px] font-bold tracking-tight tabular-nums" style="color: var(--color-text);">{{ t.value }}</p>
            <p class="text-[12px] mt-0.5" style="color: var(--color-text-secondary);">{{ t.label }} · {{ selectedYear }}</p>
          </div>
        </div>

        <!-- Month-by-month -->
        <h2 class="text-[13px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">{{ selectedYear }} payslips</h2>
        <div v-if="!entriesForYear.length" class="rounded-2xl border px-6 py-10 text-center" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[13px]" style="color: var(--color-text-secondary);">No payslips in {{ selectedYear }}.</p>
        </div>
        <div v-else class="rounded-2xl border divide-y" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }">
          <div v-for="e in entriesForYear" :key="e.id" class="flex items-center justify-between gap-3 px-4 py-3.5">
            <div class="min-w-0">
              <p class="text-[13px] font-semibold" style="color: var(--color-text);">{{ fmtMonth(e.period_label) }}</p>
              <p v-if="!e.legacy" class="text-[11px] tabular-nums" style="color: var(--color-text-tertiary);">
                Allowance {{ fmtMyr(e.allowance_snapshot_myr) }} · Extras {{ fmtMyr(e.task_extras_myr) }}<span v-if="e.tasks.length"> ({{ e.tasks.length }})</span>
              </p>
              <p v-if="e.note" class="text-[11px] truncate max-w-80" style="color: var(--color-text-tertiary);">{{ e.note }}</p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
              <span class="text-[14px] font-semibold tabular-nums" style="color: var(--color-text);">{{ fmtMyr(e.gross_myr) }}</span>
              <span
                class="inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full text-[11px] font-medium whitespace-nowrap"
                :style="e.settled
                  ? { background: 'var(--status-succeeded-bg)', color: 'var(--color-success)' }
                  : { background: 'var(--status-refunded-bg)', color: 'var(--color-warning)' }">
                <span class="size-1.5 rounded-full" :style="{ background: e.settled ? 'var(--color-success)' : 'var(--color-warning)' }" aria-hidden="true" />
                {{ e.settled ? `Paid ${fmtDate(e.paid_at)}` : 'Pending' }}
              </span>
              <button v-if="!e.settled" type="button" class="btn-table-action is-accent" @click="openSettle(e)">
                <UIcon name="i-lucide-check" class="size-3.5" />Settle
              </button>
            </div>
          </div>
        </div>
      </template>
    </template>

    <!-- Settle confirmation -->
    <Teleport to="body">
      <Transition name="confirm-fade">
        <div v-if="pendingSettle" class="confirm-overlay" @click.self="pendingSettle = null">
          <div class="confirm-card" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }">
            <h2 class="text-[17px] font-bold tracking-tight mb-2" style="color: var(--color-text);">Settle {{ fmtMonth(pendingSettle.period_label) }}?</h2>
            <p class="text-[13px] leading-relaxed mb-4" style="color: var(--color-text-secondary);">
              Records {{ fmtMyr(pendingSettle.gross_myr) }} paid to {{ detail?.user.name ?? 'this teammate' }}
              <template v-if="pendingSettle.tasks.length"> and marks {{ pendingSettle.tasks.length }} linked task {{ pendingSettle.tasks.length === 1 ? 'extra' : 'extras' }} paid</template>.
              This can't be undone here.
            </p>
            <div class="mb-6">
              <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Method</span>
              <div class="flex flex-wrap gap-1.5 mt-1.5">
                <button v-for="m in methodOptions" :key="m.value" type="button" class="standard-pill" :style="settleMethod === m.value ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' } : {}" @click="settleMethod = m.value">{{ m.label }}</button>
              </div>
            </div>
            <div class="flex items-center justify-end gap-2">
              <button type="button" class="btn-pill btn-pill-ghost text-[13px]" :disabled="settling" @click="pendingSettle = null">Cancel</button>
              <button type="button" class="btn-pill btn-pill-accent text-[13px]" :disabled="settling" @click="confirmSettle">
                {{ settling ? 'Settling…' : 'Settle payslip' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.confirm-overlay {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(3px);
}
.confirm-card {
  width: 100%;
  max-width: 440px;
  border-radius: 20px;
  border-width: 1px;
  padding: 24px;
}
.confirm-fade-enter-active,
.confirm-fade-leave-active {
  transition: opacity 0.2s ease;
}
.confirm-fade-enter-from,
.confirm-fade-leave-to {
  opacity: 0;
}
@media (prefers-reduced-motion: reduce) {
  .confirm-fade-enter-active,
  .confirm-fade-leave-active { transition: none; }
}
</style>
