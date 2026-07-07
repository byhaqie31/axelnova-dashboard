<script setup lang="ts">
// Workspace › Payroll (Task 7) — a PERSON-FIRST roster with a dashboard header.
// Pick a period (month + year); the tiles summarise it (projected payroll,
// generated/pending, paid year-to-date, headcount) and each teammate is a row:
// standing allowance (decided inline here), pending task extras (from the tasks
// engine), projected gross, a per-person Generate, and a link to their payroll
// detail page (full history + yearly totals + settle). Founder-only.
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
interface RosterSummary {
  projected_total_myr: number
  generated_count: number
  pending_count: number
  paid_this_year_myr: number
  year: number | null
  headcount: number
}

// ── Period (month + year dropdowns) ────────────────────────────────────────
const now = new Date()
const selMonth = ref(now.getMonth() + 1)
const selYear = ref(now.getFullYear())
const monthItems = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
].map((label, i) => ({ label, value: i + 1 }))
const yearItems = [now.getFullYear() + 1, now.getFullYear(), now.getFullYear() - 1, now.getFullYear() - 2]
  .map(y => ({ label: String(y), value: y }))
const period = computed(() => `${selYear.value}-${String(selMonth.value).padStart(2, '0')}`)

const roster = ref<RosterRow[]>([])
const summary = ref<RosterSummary | null>(null)
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
    const res = await apiFetch<{ data: RosterRow[], summary: RosterSummary }>(`/api/v1/admin/payroll/roster?period_label=${period.value}`)
    roster.value = res.data
    summary.value = res.summary
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
watch(period, fetchRoster)

const tiles = computed(() => {
  const s = summary.value
  return [
    { key: 'projected', label: 'Projected this period', value: fmtMyr(s?.projected_total_myr ?? 0), icon: 'i-lucide-wallet', fg: 'var(--color-accent)', bg: 'var(--color-accent-soft)' },
    { key: 'generated', label: 'Generated', value: `${s?.generated_count ?? 0}`, hint: `${s?.pending_count ?? 0} pending`, icon: 'i-lucide-receipt', fg: 'var(--color-success)', bg: 'var(--status-succeeded-bg)' },
    { key: 'paid', label: `Paid in ${s?.year ?? selYear.value}`, value: fmtMyr(s?.paid_this_year_myr ?? 0), icon: 'i-lucide-badge-check', fg: 'var(--color-accent)', bg: 'var(--color-accent-soft)' },
    { key: 'head', label: 'Active team', value: `${s?.headcount ?? 0}`, icon: 'i-lucide-users', fg: 'var(--color-warning)', bg: 'var(--color-warning-soft)' },
  ]
})

// ── Inline allowance edit (PATCH /admin/users) ─────────────────────────────
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
    await apiFetch(`/api/v1/admin/users/${row.user_id}`, { method: 'PATCH', body: { monthly_allowance_myr: next } })
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

function generateDisabledReason(row: RosterRow): string | null {
  if (row.deactivated) return 'Deactivated — reactivate on Users first'
  if (row.period_taken) return `Already generated for ${period.value}`
  if (row.projected_gross_myr < 1) return 'Nothing to pay — no allowance or extras'
  return null
}
function openGenerate(row: RosterRow) {
  if (generateDisabledReason(row)) return
  pendingGenerate.value = row
  genMethod.value = 'bank_transfer'
  genNote.value = ''
}
async function confirmGenerate() {
  const row = pendingGenerate.value
  if (!row || generating.value) return
  generating.value = true
  try {
    const body: Record<string, unknown> = { user_id: row.user_id, period_label: period.value, method: genMethod.value }
    if (genNote.value.trim()) body.note = genNote.value.trim()
    await apiFetch('/api/v1/admin/payroll', { method: 'POST', body })
    toast.success('Payslip generated', `${period.value} slip for ${row.name} added.`)
    pendingGenerate.value = null
    await fetchRoster()
  }
  catch (e) {
    toast.error('Couldn’t generate the payslip', errMessage(e) ?? 'Please try again.')
  }
  finally {
    generating.value = false
  }
}
onKeyStroke('Escape', () => {
  if (pendingGenerate.value) pendingGenerate.value = null
})

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
    <div class="flex items-start justify-between gap-4 flex-wrap mb-6">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Payroll</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
          Set allowances, generate a payslip per teammate, and open anyone's full history. Amounts as agreed, no statutory calculation.
        </p>
      </div>
      <!-- Period -->
      <div class="flex items-end gap-2">
        <label class="block">
          <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Month</span>
          <AdminSelect v-model="selMonth" :items="monthItems" class="mt-1 w-36" />
        </label>
        <label class="block">
          <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Year</span>
          <AdminSelect v-model="selYear" :items="yearItems" class="mt-1 w-24" />
        </label>
      </div>
    </div>

    <!-- Founder-only surface -->
    <div v-if="forbidden" class="rounded-2xl border p-12 text-center" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <UIcon name="i-lucide-lock" class="size-8 mb-3 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
      <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">Founder only</p>
      <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">
        The payroll roster is restricted. Your own payslips are on the
        <NuxtLink to="/team/payslips" class="underline" :style="{ color: 'var(--color-accent)' }">team workspace</NuxtLink>.
      </p>
    </div>

    <template v-else>
      <!-- Dashboard tiles -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-8">
        <div v-for="t in tiles" :key="t.key" class="rounded-2xl border p-4" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <span class="size-9 rounded-xl inline-flex items-center justify-center mb-3" :style="{ background: t.bg, color: t.fg }">
            <UIcon :name="t.icon" class="size-[18px]" />
          </span>
          <p class="text-[22px] sm:text-[24px] font-bold tracking-tight tabular-nums" style="color: var(--color-text);">{{ t.value }}</p>
          <p class="text-[12px] mt-0.5" style="color: var(--color-text-secondary);">
            {{ t.label }}<span v-if="t.hint" style="color: var(--color-text-tertiary);"> · {{ t.hint }}</span>
          </p>
        </div>
      </div>

      <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>
      <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading payroll…</div>

      <div v-else-if="!roster.length" class="rounded-2xl border p-12 text-center" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
        <UIcon name="i-lucide-users" class="size-8 mb-3 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
        <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">No teammates yet</p>
        <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">Provision teammates on the Users page first.</p>
      </div>

      <!-- Roster -->
      <div v-else class="space-y-2.5">
        <div
          v-for="row in roster" :key="row.user_id"
          class="flex items-center gap-4 flex-wrap p-4 rounded-2xl border"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <!-- Person (links to detail) -->
          <div class="min-w-40 flex-1">
            <div class="flex items-center gap-2">
              <NuxtLink :to="`/admin/payroll/${row.user_id}`" class="text-[14px] font-semibold tracking-tight hover:underline" style="color: var(--color-text);">{{ row.name }}</NuxtLink>
              <span v-if="row.deactivated" class="text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded" :style="{ color: 'var(--color-text-tertiary)', background: 'var(--color-bg-secondary)' }">deactivated</span>
            </div>
            <p class="text-[12px]" style="color: var(--color-text-tertiary);">{{ roleLabel(row.role) }}</p>
          </div>

          <!-- Allowance (inline editable) -->
          <div class="w-32">
            <p class="text-[10px] uppercase tracking-wider mb-0.5" style="color: var(--color-text-tertiary);">Allowance</p>
            <div v-if="editingAllowance === row.user_id" class="flex items-center gap-1">
              <input v-model="allowanceDraft" type="number" min="0" step="1" placeholder="None" class="contact-input !py-1 !px-2 text-[13px] w-24" @keyup.enter="saveAllowance(row)" @keyup.esc="cancelEditAllowance">
              <button type="button" class="cal-mini-nav" :disabled="savingAllowance" aria-label="Save" @click="saveAllowance(row)"><UIcon name="i-lucide-check" class="size-3.5" :style="{ color: 'var(--color-success)' }" /></button>
              <button type="button" class="cal-mini-nav" aria-label="Cancel" @click="cancelEditAllowance"><UIcon name="i-lucide-x" class="size-3.5" /></button>
            </div>
            <button v-else type="button" class="inline-flex items-center gap-1.5 text-[13px] font-semibold tabular-nums group" style="color: var(--color-text);" @click="startEditAllowance(row)">
              {{ row.monthly_allowance_myr == null ? 'None' : fmtMyr(row.monthly_allowance_myr) }}
              <UIcon name="i-lucide-pencil" class="size-3 opacity-40 group-hover:opacity-100 transition-opacity" :style="{ color: 'var(--color-text-tertiary)' }" />
            </button>
          </div>

          <!-- Extras -->
          <div class="w-28">
            <p class="text-[10px] uppercase tracking-wider mb-0.5" style="color: var(--color-text-tertiary);">Task extras</p>
            <p class="text-[13px] font-semibold tabular-nums" style="color: var(--color-text);">
              {{ fmtMyr(row.pending_extras_myr) }}<span v-if="row.pending_extras_count" class="text-[11px] font-normal" style="color: var(--color-text-tertiary);"> · {{ row.pending_extras_count }}</span>
            </p>
          </div>

          <!-- Projected -->
          <div class="w-28">
            <p class="text-[10px] uppercase tracking-wider mb-0.5" style="color: var(--color-text-tertiary);">Projected</p>
            <p class="text-[13px] font-bold tabular-nums" style="color: var(--color-accent);">{{ fmtMyr(row.projected_gross_myr) }}</p>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-1.5 ml-auto">
            <button type="button" class="btn-table-action is-accent" :disabled="!!generateDisabledReason(row)" :title="generateDisabledReason(row) ?? undefined" @click="openGenerate(row)">
              <UIcon name="i-lucide-receipt" class="size-3.5" />Generate
            </button>
            <NuxtLink :to="`/admin/payroll/${row.user_id}`" class="cal-mini-nav" aria-label="Payroll details">
              <UIcon name="i-lucide-chevron-right" class="size-4" />
            </NuxtLink>
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
                <button v-for="m in methodOptions" :key="m.value" type="button" class="standard-pill" :style="genMethod === m.value ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' } : {}" @click="genMethod = m.value">{{ m.label }}</button>
              </div>
            </div>

            <label class="block mb-5">
              <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Note (optional)</span>
              <input v-model="genNote" type="text" placeholder="Internal note" class="contact-input mt-1 w-full">
            </label>

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
  </div>
</template>

<style scoped>
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
