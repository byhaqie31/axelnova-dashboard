<script setup lang="ts">
// Workspace › Payroll (Task 7, revamped) — a PERSON-FIRST roster. One card per
// teammate for the chosen period: their standing allowance (decided inline
// here — same field as the Users page), their pending task extras (swept from
// the tasks engine), the projected gross, and a per-person Generate. Each card
// expands to that member's own payslip history + Settle. Founder-only; the
// backend gate 403s anyone else. A settled payslip IS the team-comp expense
// record — not part of the client `payments` ledger.
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

function errMessage(e: unknown): string | undefined {
  return (e as { data?: { message?: string } } | null)?.data?.message
}

interface RosterRow {
  user_id: number
  name: string
  role: string
  deactivated: boolean
  monthly_allowance_myr: number | null
  pending_extras_count: number
  pending_extras_myr: number
  projected_gross_myr: number
  period_taken: boolean | null
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
  created_at: string
}

const currentPeriod = new Date().toISOString().slice(0, 7) // YYYY-MM
const period = ref(currentPeriod)

const roster = ref<RosterRow[]>([])
const loading = ref(true)
const error = ref('')
const forbidden = ref(false)

const methodOptions = [
  { value: 'bank_transfer', label: 'Bank transfer' },
  { value: 'duitnow', label: 'DuitNow' },
  { value: 'cash', label: 'Cash' },
  { value: 'other', label: 'Other' },
]

async function fetchRoster() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams()
    if (period.value.trim()) params.set('period_label', period.value.trim())
    const res = await apiFetch<{ data: RosterRow[] }>(`/api/v1/admin/payroll/roster?${params}`)
    roster.value = res.data
  }
  catch (e: unknown) {
    if ((e as { status?: number } | null)?.status === 403) forbidden.value = true
    else error.value = 'Failed to load the payroll roster. Check your session.'
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchRoster)
// Re-fetch when the period changes — it drives the "already generated" state.
let periodTimer: ReturnType<typeof setTimeout> | null = null
watch(period, () => {
  if (periodTimer) clearTimeout(periodTimer)
  periodTimer = setTimeout(fetchRoster, 300)
})

// ── Per-person payslip history (lazy — fetched on first expand) ────────────
const expanded = ref<number | null>(null)
const historyByUser = ref<Record<number, PayrollEntry[]>>({})
const historyLoading = ref<number | null>(null)

async function toggleExpand(userId: number) {
  if (expanded.value === userId) {
    expanded.value = null
    return
  }
  expanded.value = userId
  if (!historyByUser.value[userId]) await fetchHistory(userId)
}
async function fetchHistory(userId: number) {
  historyLoading.value = userId
  try {
    const res = await apiFetch<{ data: PayrollEntry[] }>(`/api/v1/admin/payroll?user_id=${userId}`)
    historyByUser.value = { ...historyByUser.value, [userId]: res.data }
  }
  catch {
    historyByUser.value = { ...historyByUser.value, [userId]: [] }
  }
  finally {
    historyLoading.value = null
  }
}

// ── Inline allowance edit (decide standing allowance; PATCH /admin/users) ──
const editingAllowance = ref<number | null>(null)
const allowanceDraft = ref('')
const savingAllowance = ref(false)

function startEditAllowance(row: RosterRow) {
  editingAllowance.value = row.user_id
  allowanceDraft.value = row.monthly_allowance_myr == null ? '' : String(row.monthly_allowance_myr)
}
function cancelEditAllowance() {
  editingAllowance.value = null
}
async function saveAllowance(row: RosterRow) {
  if (savingAllowance.value) return
  const next = allowanceDraft.value.trim() === '' ? null : Math.round(Number(allowanceDraft.value))
  if (next != null && (!Number.isFinite(next) || next < 0)) {
    toast.error('Invalid allowance', 'Enter a whole ringgit amount, or leave blank for none.')
    return
  }
  savingAllowance.value = true
  try {
    await apiFetch(`/api/v1/admin/users/${row.user_id}`, {
      method: 'PATCH',
      body: { monthly_allowance_myr: next },
    })
    // Reflect locally — allowance feeds the projected gross.
    row.monthly_allowance_myr = next
    row.projected_gross_myr = (next ?? 0) + row.pending_extras_myr
    editingAllowance.value = null
    toast.success('Allowance updated', `${row.name}'s monthly allowance saved.`)
  }
  catch (e) {
    toast.error('Couldn’t update allowance', errMessage(e) ?? 'Please try again.')
  }
  finally {
    savingAllowance.value = false
  }
}

// ── Generate a payslip for one person ──────────────────────────────────────
const pendingGenerate = ref<RosterRow | null>(null)
const genMethod = ref('bank_transfer')
const genNote = ref('')
const generating = ref(false)

function openGenerate(row: RosterRow) {
  if (row.deactivated || row.period_taken || row.projected_gross_myr < 1) return
  pendingGenerate.value = row
  genMethod.value = 'bank_transfer'
  genNote.value = ''
}
function generateDisabledReason(row: RosterRow): string | null {
  if (row.deactivated) return 'Deactivated — reactivate on Users first'
  if (row.period_taken) return `Already generated for ${period.value}`
  if (row.projected_gross_myr < 1) return 'Nothing to pay — no allowance or extras'
  return null
}
async function confirmGenerate() {
  const row = pendingGenerate.value
  if (!row || generating.value) return
  generating.value = true
  try {
    const body: Record<string, unknown> = {
      user_id: row.user_id,
      period_label: period.value.trim(),
      method: genMethod.value,
    }
    if (genNote.value.trim()) body.note = genNote.value.trim()
    await apiFetch('/api/v1/admin/payroll', { method: 'POST', body })
    toast.success('Payslip generated', `${period.value} slip for ${row.name} added.`)
    pendingGenerate.value = null
    // Drop this member's cached history — it's now stale.
    historyByUser.value = Object.fromEntries(
      Object.entries(historyByUser.value).filter(([id]) => Number(id) !== row.user_id),
    )
    await fetchRoster()
    if (expanded.value === row.user_id) await fetchHistory(row.user_id)
  }
  catch (e) {
    toast.error('Couldn’t generate the payslip', errMessage(e) ?? 'Please try again.')
  }
  finally {
    generating.value = false
  }
}

// ── Settle a payslip ───────────────────────────────────────────────────────
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
    await fetchHistory(entry.user_id)
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
  if (pendingGenerate.value) pendingGenerate.value = null
  else if (pendingSettle.value) pendingSettle.value = null
})

function fmtDate(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
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
    <div class="mb-8">
      <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Payroll</h1>
      <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
        One row per teammate — set their allowance, see pending task extras, and generate a payslip each. Amounts as agreed, no statutory calculation.
      </p>
    </div>

    <!-- Founder-only surface: the backend gate stops anyone else's data (403). -->
    <div
      v-if="forbidden" class="rounded-2xl border p-12 text-center"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <UIcon name="i-lucide-lock" class="size-8 mb-3 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
      <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">Founder only</p>
      <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">
        The full payroll roster is restricted. Your own payslips are on the
        <NuxtLink to="/team/payslips" class="underline" :style="{ color: 'var(--color-accent)' }">team workspace</NuxtLink>.
      </p>
    </div>

    <template v-else>
      <!-- Period selector -->
      <div class="flex flex-wrap items-end gap-3 mb-6">
        <label class="block">
          <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Period</span>
          <input v-model="period" type="text" placeholder="e.g. 2026-07" class="contact-input mt-1 w-40">
        </label>
        <p v-if="roster.length" class="ml-auto text-[12px]" style="color: var(--color-text-tertiary);">{{ roster.length }} teammates</p>
      </div>

      <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>
      <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading payroll…</div>

      <div
        v-else-if="!roster.length" class="rounded-2xl border p-12 text-center"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
        <UIcon name="i-lucide-users" class="size-8 mb-3 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
        <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">No teammates yet</p>
        <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">Provision teammates on the Users page first.</p>
      </div>

      <!-- Roster -->
      <div v-else class="space-y-2.5">
        <div
          v-for="row in roster" :key="row.user_id"
          class="rounded-2xl border overflow-hidden"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <!-- Summary row -->
          <div class="flex items-center gap-4 flex-wrap p-4">
            <!-- Person -->
            <div class="min-w-40 flex-1">
              <div class="flex items-center gap-2">
                <p class="text-[14px] font-semibold tracking-tight" style="color: var(--color-text);">{{ row.name }}</p>
                <span v-if="row.deactivated" class="text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded" :style="{ color: 'var(--color-text-tertiary)', background: 'var(--color-bg-secondary)' }">deactivated</span>
              </div>
              <p class="text-[12px]" style="color: var(--color-text-tertiary);">{{ roleLabel(row.role) }}</p>
            </div>

            <!-- Allowance (inline editable) -->
            <div class="w-32">
              <p class="text-[10px] uppercase tracking-wider mb-0.5" style="color: var(--color-text-tertiary);">Allowance</p>
              <div v-if="editingAllowance === row.user_id" class="flex items-center gap-1">
                <input
                  v-model="allowanceDraft" type="number" min="0" step="1" placeholder="None"
                  class="contact-input !py-1 !px-2 text-[13px] w-24" @keyup.enter="saveAllowance(row)" @keyup.esc="cancelEditAllowance">
                <button type="button" class="cal-mini-nav" :disabled="savingAllowance" aria-label="Save" @click="saveAllowance(row)">
                  <UIcon name="i-lucide-check" class="size-3.5" :style="{ color: 'var(--color-success)' }" />
                </button>
                <button type="button" class="cal-mini-nav" aria-label="Cancel" @click="cancelEditAllowance">
                  <UIcon name="i-lucide-x" class="size-3.5" />
                </button>
              </div>
              <button
                v-else type="button" class="inline-flex items-center gap-1.5 text-[13px] font-semibold tabular-nums group"
                style="color: var(--color-text);" @click="startEditAllowance(row)">
                {{ row.monthly_allowance_myr == null ? 'None' : fmtMyr(row.monthly_allowance_myr) }}
                <UIcon name="i-lucide-pencil" class="size-3 opacity-40 group-hover:opacity-100 transition-opacity" :style="{ color: 'var(--color-text-tertiary)' }" />
              </button>
            </div>

            <!-- Extras -->
            <div class="w-28">
              <p class="text-[10px] uppercase tracking-wider mb-0.5" style="color: var(--color-text-tertiary);">Task extras</p>
              <p class="text-[13px] font-semibold tabular-nums" style="color: var(--color-text);">
                {{ fmtMyr(row.pending_extras_myr) }}
                <span v-if="row.pending_extras_count" class="text-[11px] font-normal" style="color: var(--color-text-tertiary);">· {{ row.pending_extras_count }}</span>
              </p>
            </div>

            <!-- Gross -->
            <div class="w-28">
              <p class="text-[10px] uppercase tracking-wider mb-0.5" style="color: var(--color-text-tertiary);">Projected</p>
              <p class="text-[13px] font-bold tabular-nums" style="color: var(--color-accent);">{{ fmtMyr(row.projected_gross_myr) }}</p>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-1.5 ml-auto">
              <button
                type="button" class="btn-table-action is-accent"
                :disabled="!!generateDisabledReason(row)" :title="generateDisabledReason(row) ?? undefined"
                @click="openGenerate(row)">
                <UIcon name="i-lucide-receipt" class="size-3.5" />Generate
              </button>
              <button type="button" class="cal-mini-nav" :aria-label="expanded === row.user_id ? 'Collapse' : 'History'" @click="toggleExpand(row.user_id)">
                <UIcon :name="expanded === row.user_id ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'" class="size-4" />
              </button>
            </div>
          </div>

          <!-- Expanded: this person's payslip history -->
          <div v-if="expanded === row.user_id" class="border-t px-4 py-3" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
            <p v-if="historyLoading === row.user_id" class="text-[12px] py-2" style="color: var(--color-text-tertiary);">Loading history…</p>
            <template v-else>
              <p class="text-[11px] font-semibold uppercase tracking-wider mb-2" style="color: var(--color-text-tertiary);">Payslip history</p>
              <p v-if="!historyByUser[row.user_id]?.length" class="text-[12px] py-2" style="color: var(--color-text-secondary);">No payslips generated yet.</p>
              <div v-else class="divide-y" :style="{ borderColor: 'var(--color-border)' }">
                <div v-for="e in historyByUser[row.user_id]" :key="e.id" class="flex items-center justify-between gap-3 py-2.5">
                  <div class="min-w-0">
                    <p class="text-[13px] font-medium" style="color: var(--color-text);">{{ e.period_label }}</p>
                    <p v-if="!e.legacy" class="text-[11px] tabular-nums" style="color: var(--color-text-tertiary);">
                      Allowance {{ fmtMyr(e.allowance_snapshot_myr) }} · Extras {{ fmtMyr(e.task_extras_myr) }}
                    </p>
                    <p v-if="e.note" class="text-[11px] truncate max-w-64" style="color: var(--color-text-tertiary);">{{ e.note }}</p>
                  </div>
                  <div class="flex items-center gap-3 shrink-0">
                    <span class="text-[13px] font-semibold tabular-nums" style="color: var(--color-text);">{{ fmtMyr(e.gross_myr) }}</span>
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
          </div>
        </div>
      </div>
    </template>

    <!-- Generate confirmation -->
    <Teleport to="body">
      <Transition name="confirm-fade">
        <div v-if="pendingGenerate" class="confirm-overlay" @click.self="pendingGenerate = null">
          <div class="confirm-card" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }">
            <h2 class="text-[17px] font-bold tracking-tight mb-1" style="color: var(--color-text);">Generate {{ period }} payslip</h2>
            <p class="text-[13px] mb-4" style="color: var(--color-text-secondary);">For <span class="font-medium" style="color: var(--color-text);">{{ pendingGenerate.name }}</span>.</p>

            <div class="rounded-xl border p-4 mb-4 grid grid-cols-3 gap-3" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }">
              <div>
                <p class="text-[11px]" style="color: var(--color-text-tertiary);">Allowance</p>
                <p class="text-[14px] font-semibold tabular-nums" style="color: var(--color-text);">{{ pendingGenerate.monthly_allowance_myr == null ? 'None' : fmtMyr(pendingGenerate.monthly_allowance_myr) }}</p>
              </div>
              <div>
                <p class="text-[11px]" style="color: var(--color-text-tertiary);">Extras ({{ pendingGenerate.pending_extras_count }})</p>
                <p class="text-[14px] font-semibold tabular-nums" style="color: var(--color-text);">{{ fmtMyr(pendingGenerate.pending_extras_myr) }}</p>
              </div>
              <div>
                <p class="text-[11px]" style="color: var(--color-text-tertiary);">Gross</p>
                <p class="text-[14px] font-bold tabular-nums" style="color: var(--color-accent);">{{ fmtMyr(pendingGenerate.projected_gross_myr) }}</p>
              </div>
            </div>

            <div class="mb-4">
              <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Method</span>
              <div class="flex flex-wrap gap-1.5 mt-1.5">
                <button
                  v-for="m in methodOptions" :key="m.value" type="button" class="standard-pill"
                  :style="genMethod === m.value ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' } : {}"
                  @click="genMethod = m.value">{{ m.label }}</button>
              </div>
            </div>

            <label class="block mb-5">
              <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Note (optional)</span>
              <input v-model="genNote" type="text" placeholder="Internal note" class="contact-input mt-1 w-full">
            </label>

            <p class="text-[11px] mb-4" style="color: var(--color-text-tertiary);">Links this member's pending task extras to the slip; settling later stamps them paid. Record-only — EPF/SOCSO/EIS/PCB stay with the payroll provider.</p>

            <div class="flex items-center justify-end gap-2">
              <button type="button" class="btn-pill btn-pill-ghost text-[13px]" :disabled="generating" @click="pendingGenerate = null">Cancel</button>
              <button type="button" class="btn-pill btn-pill-accent text-[13px]" :disabled="generating" @click="confirmGenerate">
                {{ generating ? 'Generating…' : 'Generate payslip' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

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
/* Small round icon button (reused from the calendar mini-nav idiom). */
.cal-mini-nav {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 9999px;
  color: var(--color-text-secondary);
  transition: background 0.15s ease, color 0.15s ease;
}
.cal-mini-nav:hover {
  background: var(--color-bg-secondary);
  color: var(--color-text);
}
.cal-mini-nav:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

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
