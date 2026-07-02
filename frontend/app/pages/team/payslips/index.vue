<script setup lang="ts">
// Phase 5 — own payslips from the record-only payroll ledger. The endpoint is
// scoped server-side to the session's own rows; every internal role (founder →
// engineer) reads the same view of their own money here.
definePageMeta({ layout: 'team', middleware: 'team-auth' })
useHead({ title: 'Payslips — Team' })

const { apiFetch } = useTeamAuth()

interface PayrollEntry {
  id: number
  period_label: string
  gross_myr: number
  paid_at: string | null
  method: string | null
  note: string | null
  created_at: string
}

const entries = ref<PayrollEntry[]>([])
const meta = ref<{ current_page: number, last_page: number, total: number } | null>(null)
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
    const res = await apiFetch<{ data: PayrollEntry[], meta: any }>(`/api/v1/team/payslips?page=${page.value}`)
    entries.value = res.data
    meta.value = res.meta
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
function fmtMyr(amount: number) {
  return `RM ${Number(amount).toLocaleString('en-MY')}`
}
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <h1 class="text-[28px] font-bold tracking-tight mb-1" style="color: var(--color-text);">Payslips</h1>
    <p class="text-[14px] mb-8" style="color: var(--color-text-secondary);">Your payroll records — amounts as agreed, record-only.</p>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading payslips…</div>

    <div v-else-if="!entries.length" class="rounded-2xl border px-6 py-12 text-center"
      :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
      <span
        class="size-12 rounded-2xl inline-flex items-center justify-center mb-4"
        :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
      >
        <UIcon name="i-lucide-wallet" class="size-6" />
      </span>
      <p class="text-[15px] font-semibold tracking-tight mb-1" style="color: var(--color-text);">No payslips yet</p>
      <p class="text-[13px] max-w-sm mx-auto leading-relaxed" style="color: var(--color-text-secondary);">
        Entries appear here once payroll is recorded for you.
      </p>
    </div>

    <!-- Statement-style rows: period + payment detail left, gross right. -->
    <div v-else class="rounded-2xl border divide-y"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <div v-for="e in entries" :key="e.id" class="flex items-center justify-between gap-4 px-5 py-4"
        :style="{ borderColor: 'var(--color-border)' }">
        <div class="min-w-0">
          <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-text)' }">{{ e.period_label }}</p>
          <p class="text-[11px] truncate" :style="{ color: 'var(--color-text-tertiary)' }">
            <template v-if="fmtDate(e.paid_at)">Paid {{ fmtDate(e.paid_at) }}</template>
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
  </div>
</template>
