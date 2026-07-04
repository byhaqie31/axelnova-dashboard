<script setup lang="ts">
// Workspace › Payroll (Task 7) — the founder's cockpit over payslips. A payslip
// is GENERATED, not hand-keyed: pick a member, name the period, and the server
// snapshots their monthly allowance + sweeps up their pending task extras into
// one gross. A live preview shows what the slip would carry before you commit.
// The list itemises allowance / extras / gross and offers Settle (stamps paid +
// flips the linked task extras to paid). Legacy rows (pre-Task-7) render
// gross-only — no breakdown to show. Founder-only; the backend gate 403s anyone
// else. The settled payslip IS the team-comp expense record (no separate P&L
// module exists), and it is NOT part of the client `payments` revenue ledger.
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

// Typed extraction of the API error message (avoids `catch (e: any)`).
function errMessage(e: unknown): string | undefined {
  return (e as { data?: { message?: string } } | null)?.data?.message
}

interface LinkedTask { id: number, title: string, pay_amount_myr: number | null }
interface PayrollEntry {
  id: number
  user_id: number
  user_name: string | null
  period_label: string
  allowance_snapshot_myr: number | null
  task_extras_myr: number
  gross_myr: number
  legacy: boolean
  settled: boolean
  paid_at: string | null
  method: string | null
  note: string | null
  tasks: LinkedTask[]
  created_by_name: string | null
  created_at: string
}
interface Teammate { id: number, name: string, role: string, deactivated_at: string | null }
interface Preview {
  user_id: number
  user_name: string
  monthly_allowance_myr: number | null
  pending_extras_count: number
  pending_extras_myr: number
  projected_gross_myr: number
  /** True when a slip already exists for the previewed (user, period). Null if no period sent. */
  period_taken: boolean | null
}

interface ListMeta { current_page: number, last_page: number, total: number }

const entries = ref<PayrollEntry[]>([])
const meta = ref<ListMeta | null>(null)
const teammates = ref<Teammate[]>([])
const loading = ref(true)
const error = ref('')
const forbidden = ref(false)

const filters = reactive({ user_id: '', page: 1 })

const showForm = ref(false)
const saving = ref(false)
// Payslips are monthly — prefill the label with the current YYYY-MM ('2026-07').
const currentPeriod = new Date().toISOString().slice(0, 7)
const form = reactive({
  user_id: '',
  period_label: currentPeriod,
  method: 'bank_transfer',
  note: '',
})

// Live generation preview for the selected member.
const preview = ref<Preview | null>(null)
const previewLoading = ref(false)

const methodOptions = [
  { value: 'bank_transfer', label: 'Bank transfer' },
  { value: 'duitnow', label: 'DuitNow' },
  { value: 'cash', label: 'Cash' },
  { value: 'other', label: 'Other' },
]

// The LEDGER filter keeps everyone — a deactivated teammate's historical
// payslips stay filterable — but tags them so the state is visible.
const teammateItems = computed(() => [
  { label: 'All teammates', value: '' },
  ...teammates.value.map(u => ({
    label: `${u.name} (${u.role})${u.deactivated_at ? ' — deactivated' : ''}`,
    value: String(u.id),
  })),
])
// The GENERATION picker excludes deactivated accounts (Task 8 lockout) — the
// backend rejects generating a payslip for one, so don't offer it.
const formTeammateItems = computed(() => [
  { label: '— Select teammate —', value: '' },
  ...teammates.value.filter(u => !u.deactivated_at)
    .map(u => ({ label: `${u.name} (${u.role})`, value: String(u.id) })),
])

async function fetchEntries() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams()
    if (filters.user_id) params.set('user_id', filters.user_id)
    params.set('page', String(filters.page))

    const res = await apiFetch<{ data: PayrollEntry[], meta: ListMeta }>(`/api/v1/admin/payroll?${params}`)
    entries.value = res.data
    meta.value = res.meta
  }
  catch (e: unknown) {
    if ((e as { status?: number } | null)?.status === 403) forbidden.value = true
    else error.value = 'Failed to load the payroll ledger. Check your session.'
  }
  finally {
    loading.value = false
  }
}

async function fetchTeammates() {
  try {
    teammates.value = await apiFetch<Teammate[]>('/api/v1/admin/users')
  }
  catch {
    // Founder-only endpoint; the ledger fetch surfaces the 403 state.
  }
}

async function fetchPreview() {
  preview.value = null
  if (!form.user_id) return
  previewLoading.value = true
  try {
    const params = new URLSearchParams({ user_id: form.user_id })
    if (form.period_label.trim()) params.set('period_label', form.period_label.trim())
    preview.value = await apiFetch<Preview>(`/api/v1/admin/payroll/preview?${params}`)
  }
  catch {
    // Non-fatal — the generate call re-validates server-side.
  }
  finally {
    previewLoading.value = false
  }
}

onMounted(() => {
  fetchEntries()
  fetchTeammates()
})

watch(() => filters.user_id, () => {
  if (filters.page !== 1) filters.page = 1
  else fetchEntries()
})
watch(() => filters.page, () => fetchEntries())
// Refresh the preview when the member OR the period changes (the period drives
// the already-exists warning).
watch(() => [form.user_id, form.period_label], fetchPreview)

async function generate() {
  if (!form.user_id) {
    toast.error('Pick a teammate', 'Every payslip belongs to someone.')
    return
  }
  if (!form.period_label.trim()) {
    toast.error('Name the period', 'e.g. 2026-07.')
    return
  }
  saving.value = true
  try {
    const body: Record<string, unknown> = {
      user_id: Number(form.user_id),
      period_label: form.period_label.trim(),
      method: form.method,
    }
    if (form.note) body.note = form.note
    await apiFetch('/api/v1/admin/payroll', { method: 'POST', body })
    toast.success('Payslip generated', `${form.period_label} slip added to the ledger.`)
    form.user_id = ''
    form.note = ''
    form.period_label = currentPeriod
    preview.value = null
    showForm.value = false
    filters.page = 1
    fetchEntries()
  }
  catch (e) {
    toast.error('Couldn’t generate the payslip', errMessage(e) ?? 'Please try again.')
  }
  finally {
    saving.value = false
  }
}

// ── Settle — stamp paid + flip the linked task extras to paid.
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
    await apiFetch(`/api/v1/admin/payroll/${entry.id}/settle`, {
      method: 'POST',
      body: { method: settleMethod.value },
    })
    toast.success('Payslip settled', `${entry.period_label} marked paid.`)
    pendingSettle.value = null
    fetchEntries()
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
// Whole-ringgit ledger (record-only) — no cents.
function fmtMyr(amount: number | null) {
  if (amount == null) return '—'
  return `RM ${Number(amount).toLocaleString('en-MY')}`
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Payroll</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">Generate a payslip per member per period — allowance snapshot + settled task extras. Amounts as agreed, no statutory calculation.</p>
      </div>
      <button v-if="!forbidden" type="button" class="btn-pill btn-pill-primary text-[13px]" @click="showForm = !showForm">
        <UIcon :name="showForm ? 'i-lucide-x' : 'i-lucide-plus'" class="size-4" />
        {{ showForm ? 'Close' : 'Generate payslip' }}
      </button>
    </div>

    <!-- Founder-only surface: the backend gate stops anyone else's data (403). -->
    <div
v-if="forbidden" class="rounded-2xl border p-12 text-center"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <UIcon name="i-lucide-lock" class="size-8 mb-3 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
      <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">Founder only</p>
      <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">
        The full payroll ledger is restricted. Your own payslips are on the
        <NuxtLink to="/team/payslips" class="underline" :style="{ color: 'var(--color-accent)' }">team workspace</NuxtLink>.
      </p>
    </div>

    <template v-else>
      <!-- Generation form -->
      <div
v-if="showForm" class="rounded-2xl border p-6 space-y-5 mb-8"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <div class="grid sm:grid-cols-2 gap-3">
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Teammate</span>
            <AdminSelect v-model="form.user_id" class="mt-1" :items="formTeammateItems" />
          </label>
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Period</span>
            <input v-model="form.period_label" type="text" placeholder="e.g. 2026-07" class="contact-input mt-1 w-full">
          </label>
        </div>

        <!-- Live preview — what this slip would carry right now. -->
        <div
v-if="form.user_id" class="rounded-xl border p-4"
          :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
          <p v-if="previewLoading" class="text-[12px]" style="color: var(--color-text-tertiary);">Calculating preview…</p>
          <template v-else-if="preview">
            <p class="text-[11px] font-medium uppercase tracking-wider mb-3" style="color: var(--color-text-tertiary);">This slip will carry</p>
            <div class="grid grid-cols-3 gap-3">
              <div>
                <p class="text-[11px]" style="color: var(--color-text-tertiary);">Allowance</p>
                <p class="text-[14px] font-semibold tabular-nums" style="color: var(--color-text);">
                  {{ preview.monthly_allowance_myr == null ? 'None on file' : fmtMyr(preview.monthly_allowance_myr) }}
                </p>
              </div>
              <div>
                <p class="text-[11px]" style="color: var(--color-text-tertiary);">Task extras ({{ preview.pending_extras_count }})</p>
                <p class="text-[14px] font-semibold tabular-nums" style="color: var(--color-text);">{{ fmtMyr(preview.pending_extras_myr) }}</p>
              </div>
              <div>
                <p class="text-[11px]" style="color: var(--color-text-tertiary);">Gross</p>
                <p class="text-[14px] font-bold tabular-nums" style="color: var(--color-accent);">{{ fmtMyr(preview.projected_gross_myr) }}</p>
              </div>
            </div>
            <p v-if="preview.projected_gross_myr < 1" class="text-[11px] mt-3" style="color: var(--color-warning);">
              Nothing to pay — no allowance on file and no pending task extras.
            </p>
            <p v-if="preview.period_taken" class="text-[11px] mt-3" style="color: var(--color-danger);">
              A payslip for {{ form.period_label.trim() }} already exists for this teammate — generating will be rejected.
            </p>
          </template>
        </div>

        <div>
          <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Method</span>
          <div class="flex flex-wrap gap-1.5 mt-1.5">
            <button
v-for="m in methodOptions" :key="m.value" type="button" class="standard-pill"
              :style="form.method === m.value ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' } : {}"
              @click="form.method = m.value">{{ m.label }}</button>
          </div>
        </div>

        <label class="block">
          <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Note (optional)</span>
          <input v-model="form.note" type="text" placeholder="Internal note" class="contact-input mt-1 w-full">
        </label>

        <p class="text-[11px]" style="color: var(--color-text-tertiary);">Generating links the member's pending task extras to this slip; settling later stamps them paid. Record-only — EPF/SOCSO/EIS/PCB stay with the payroll provider.</p>

        <button
type="button" class="btn-pill btn-pill-primary w-full justify-center text-[13px]"
          :class="{ 'opacity-50': saving }" :disabled="saving" @click="generate">
          {{ saving ? 'Generating…' : 'Generate payslip' }}
        </button>
      </div>

      <!-- Filter -->
      <div class="flex flex-wrap items-center gap-3 mb-6">
        <AdminSelect v-model="filters.user_id" :items="teammateItems" class="w-56" />
        <span v-if="meta" class="ml-auto text-[12px]" style="color: var(--color-text-tertiary);">{{ meta.total }} entries</span>
      </div>

      <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

      <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading payroll…</div>

      <div
v-else-if="!entries.length" class="rounded-2xl border p-12 text-center"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
        <UIcon name="i-lucide-banknote" class="size-8 mb-3 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
        <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">No payslips yet</p>
        <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">Generate the first one with the button above.</p>
      </div>

      <!-- Desktop: table -->
      <div v-else class="hidden md:block admin-table-card">
        <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead>
            <tr>
              <th
v-for="h in ['Teammate', 'Period', 'Allowance', 'Extras', 'Gross', 'State', '']" :key="h"
                class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
                {{ h }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="e in entries" :key="e.id" class="admin-table-row" style="cursor: default;">
              <td class="px-4 py-3.5">
                <p class="text-[13px] font-medium" style="color: var(--color-text);">{{ e.user_name ?? '—' }}</p>
                <p v-if="e.note" class="text-[11px] truncate max-w-52" style="color: var(--color-text-tertiary);">{{ e.note }}</p>
              </td>
              <td class="px-4 py-3.5 text-[13px]" style="color: var(--color-text);">{{ e.period_label }}</td>
              <!-- Legacy rows have no breakdown — render gross-only, dashes for the parts. -->
              <td class="px-4 py-3.5 text-[13px] tabular-nums" style="color: var(--color-text-secondary);">
                {{ e.legacy ? '—' : fmtMyr(e.allowance_snapshot_myr) }}
              </td>
              <td class="px-4 py-3.5 text-[13px] tabular-nums" style="color: var(--color-text-secondary);">
                {{ e.legacy ? '—' : fmtMyr(e.task_extras_myr) }}
              </td>
              <td class="px-4 py-3.5">
                <span class="text-[13px] font-semibold tabular-nums" style="color: var(--color-text);">{{ fmtMyr(e.gross_myr) }}</span>
              </td>
              <td class="px-4 py-3.5">
                <span
                  class="inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full text-[11px] font-medium"
                  :style="e.settled
                    ? { background: 'var(--status-succeeded-bg)', color: 'var(--color-success)' }
                    : { background: 'var(--status-refunded-bg)', color: 'var(--color-warning)' }">
                  <span class="size-1.5 rounded-full" :style="{ background: e.settled ? 'var(--color-success)' : 'var(--color-warning)' }" aria-hidden="true" />
                  {{ e.settled ? `Paid ${fmtDate(e.paid_at)}` : 'Pending' }}
                </span>
              </td>
              <td class="px-4 py-3.5 text-right">
                <button
                  v-if="!e.settled" type="button" class="btn-pill btn-pill-ghost text-[12px]"
                  @click="openSettle(e)">Settle</button>
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>

      <!-- Mobile: cards -->
      <div v-if="entries.length && !loading" class="md:hidden space-y-2.5">
        <div
v-for="e in entries" :key="e.id" class="rounded-xl border p-4"
          :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
          <div class="flex items-start justify-between gap-3 mb-1">
            <span class="text-[13px] font-semibold leading-tight" :style="{ color: 'var(--color-text)' }">{{ e.user_name ?? '—' }}</span>
            <span class="text-[14px] font-semibold tabular-nums" :style="{ color: 'var(--color-text)' }">{{ fmtMyr(e.gross_myr) }}</span>
          </div>
          <p class="text-[13px]" :style="{ color: 'var(--color-text-secondary)' }">{{ e.period_label }}</p>
          <p v-if="!e.legacy" class="text-[11px] mt-1 tabular-nums" :style="{ color: 'var(--color-text-tertiary)' }">
            Allowance {{ fmtMyr(e.allowance_snapshot_myr) }} · Extras {{ fmtMyr(e.task_extras_myr) }}
          </p>
          <div class="flex items-center justify-between gap-3 pt-2 mt-2 border-t" :style="{ borderColor: 'var(--color-border)' }">
            <span class="text-[11px]" :style="{ color: e.settled ? 'var(--color-success)' : 'var(--color-warning)' }">
              {{ e.settled ? `Paid ${fmtDate(e.paid_at)}` : 'Pending' }}
            </span>
            <button v-if="!e.settled" type="button" class="btn-pill btn-pill-ghost text-[12px]" @click="openSettle(e)">Settle</button>
          </div>
        </div>
      </div>

      <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
        <button :disabled="filters.page <= 1" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page--">← Prev</button>
        <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ filters.page }} / {{ meta.last_page }}</span>
        <button :disabled="filters.page >= meta.last_page" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page++">Next →</button>
      </div>
    </template>

    <!-- Settle confirmation -->
    <Teleport to="body">
      <Transition name="confirm-fade">
        <div v-if="pendingSettle" class="confirm-overlay" @click.self="pendingSettle = null">
          <div class="confirm-card" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }">
            <h2 class="text-[17px] font-bold tracking-tight mb-2" style="color: var(--color-text);">Settle {{ pendingSettle.period_label }}?</h2>
            <p class="text-[13px] leading-relaxed mb-4" style="color: var(--color-text-secondary);">
              Records {{ fmtMyr(pendingSettle.gross_myr) }} paid to {{ pendingSettle.user_name ?? 'this teammate' }}
              <template v-if="pendingSettle.tasks.length"> and marks {{ pendingSettle.tasks.length }} linked task {{ pendingSettle.tasks.length === 1 ? 'extra' : 'extras' }} paid</template>.
              This can't be undone here.
            </p>
            <div class="mb-6">
              <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Method</span>
              <div class="flex flex-wrap gap-1.5 mt-1.5">
                <button
v-for="m in methodOptions" :key="m.value" type="button" class="standard-pill"
                  :style="settleMethod === m.value ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' } : {}"
                  @click="settleMethod = m.value">{{ m.label }}</button>
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
/* Confirm dialog (§12, same idiom as /admin/tasks). */
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
  max-width: 420px;
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
