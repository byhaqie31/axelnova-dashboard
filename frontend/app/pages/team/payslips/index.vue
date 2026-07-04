<script setup lang="ts">
// Task 7 — own payslips from the in-system payroll ledger. The endpoint is scoped
// server-side to the session's own rows and returns two blocks: `pending_extras`
// (completed-with-pay tasks not yet on a slip — money owed, shown on top) and the
// payslip list itself, each itemised as allowance snapshot + task extras = gross.
// Legacy rows (pre-Task-7) carry a gross only and render without a breakdown.
definePageMeta({ layout: 'team', middleware: 'team-auth' })
useHead({ title: 'Payslips — Team' })

const { apiFetch } = useTeamAuth()

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
  created_at: string
}
interface PendingTask { id: number, title: string, pay_amount_myr: number | null, completed_at: string | null }

interface ListMeta { current_page: number, last_page: number, total: number }

const entries = ref<PayrollEntry[]>([])
const meta = ref<ListMeta | null>(null)
const pendingTasks = ref<PendingTask[]>([])
const pendingTotal = ref(0)
const loading = ref(true)
const error = ref('')
const page = ref(1)

const methodLabels: Record<string, string> = {
  bank_transfer: 'Bank transfer',
  duitnow: 'DuitNow',
  cash: 'Cash',
  other: 'Other',
}

async function fetchEntries() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{
      payslips: { data: PayrollEntry[], meta: ListMeta }
      pending_extras: { tasks: PendingTask[], total_myr: number }
    }>(`/api/v1/team/payslips?page=${page.value}`)
    entries.value = res.payslips.data
    meta.value = res.payslips.meta
    pendingTasks.value = res.pending_extras.tasks
    pendingTotal.value = res.pending_extras.total_myr
  }
  catch {
    error.value = 'Failed to load your payslips. Check your session.'
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchEntries)
watch(page, () => fetchEntries())

function fmtDate(iso: string | null) {
  if (!iso) return null
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}
// Whole-ringgit ledger (record-only) — no cents.
function fmtMyr(amount: number | null) {
  if (amount == null) return '—'
  return `RM ${Number(amount).toLocaleString('en-MY')}`
}
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <h1 class="text-[28px] font-bold tracking-tight mb-1" style="color: var(--color-text);">Payslips</h1>
    <p class="text-[14px] mb-8" style="color: var(--color-text-secondary);">Your payroll records — allowance plus settled task extras, amounts as agreed.</p>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading payslips…</div>

    <template v-else>
      <!-- Pending extras — completed-with-pay tasks not yet on a payslip. -->
      <div
v-if="pendingTasks.length" class="rounded-2xl border p-5 mb-8"
        :style="{ borderColor: 'var(--color-warning)', background: 'var(--color-warning-soft)' }">
        <div class="flex items-center justify-between gap-3 mb-3">
          <div class="flex items-center gap-2">
            <UIcon name="i-lucide-hourglass" class="size-4" :style="{ color: 'var(--color-warning)' }" />
            <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-text)' }">Pending extras</p>
          </div>
          <span class="text-[14px] font-bold tabular-nums" :style="{ color: 'var(--color-warning)' }">{{ fmtMyr(pendingTotal) }}</span>
        </div>
        <p class="text-[11px] mb-3" :style="{ color: 'var(--color-text-secondary)' }">Completed task bonuses owed — they'll land on a future payslip.</p>
        <div class="space-y-1.5">
          <div v-for="t in pendingTasks" :key="t.id" class="flex items-center justify-between gap-3">
            <span class="text-[12px] truncate" :style="{ color: 'var(--color-text)' }">{{ t.title }}</span>
            <span class="text-[12px] font-semibold tabular-nums shrink-0" :style="{ color: 'var(--color-text)' }">{{ fmtMyr(t.pay_amount_myr) }}</span>
          </div>
        </div>
      </div>

      <div
v-if="!entries.length" class="rounded-2xl border px-6 py-12 text-center"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <span
          class="size-12 rounded-2xl inline-flex items-center justify-center mb-4"
          :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
        >
          <UIcon name="i-lucide-wallet" class="size-6" />
        </span>
        <p class="text-[15px] font-semibold tracking-tight mb-1" style="color: var(--color-text);">No payslips yet</p>
        <p class="text-[13px] max-w-sm mx-auto leading-relaxed" style="color: var(--color-text-secondary);">
          Entries appear here once payroll is generated for you.
        </p>
      </div>

      <!-- Statement-style rows: period + breakdown left, gross right. -->
      <div
v-else class="rounded-2xl border divide-y"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
        <div
v-for="e in entries" :key="e.id" class="flex items-center justify-between gap-4 px-5 py-4"
          :style="{ borderColor: 'var(--color-border)' }">
          <div class="min-w-0">
            <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-text)' }">{{ e.period_label }}</p>
            <!-- Breakdown line for Task-7 rows; legacy rows drop it. -->
            <p v-if="!e.legacy" class="text-[11px] tabular-nums" :style="{ color: 'var(--color-text-secondary)' }">
              Allowance {{ fmtMyr(e.allowance_snapshot_myr) }} · Extras {{ fmtMyr(e.task_extras_myr) }}
            </p>
            <p class="text-[11px] truncate" :style="{ color: 'var(--color-text-tertiary)' }">
              <template v-if="e.settled">Paid {{ fmtDate(e.paid_at) }}</template>
              <template v-else>Not paid yet</template>
              <template v-if="e.method"> · {{ methodLabels[e.method] ?? e.method }}</template>
              <template v-if="e.note"> · {{ e.note }}</template>
            </p>
          </div>
          <span class="text-[14px] font-semibold tabular-nums shrink-0" :style="{ color: 'var(--color-text)' }">{{ fmtMyr(e.gross_myr) }}</span>
        </div>
      </div>

      <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
        <button :disabled="page <= 1" class="btn-pill btn-pill-ghost text-[12px]" @click="page--">← Prev</button>
        <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ page }} / {{ meta.last_page }}</span>
        <button :disabled="page >= meta.last_page" class="btn-pill btn-pill-ghost text-[12px]" @click="page++">Next →</button>
      </div>
    </template>
  </div>
</template>
