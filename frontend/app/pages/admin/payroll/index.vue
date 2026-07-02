<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

interface PayrollEntry {
  id: number
  user_id: number
  user_name: string | null
  period_label: string
  gross_myr: number
  paid_at: string | null
  method: string | null
  note: string | null
  created_by_name: string | null
  created_at: string
}
interface Teammate { id: number, name: string, role: string }

const entries = ref<PayrollEntry[]>([])
const meta = ref<{ current_page: number, last_page: number, total: number } | null>(null)
const teammates = ref<Teammate[]>([])
const loading = ref(true)
const error = ref('')
const forbidden = ref(false)

const filters = reactive({ user_id: '', page: 1 })

const showForm = ref(false)
const saving = ref(false)
// Payroll is monthly — prefill the label with the current month ('Jul 2026').
const currentPeriod = new Date().toLocaleDateString('en-MY', { month: 'short', year: 'numeric' })
const form = reactive({
  user_id: '',
  period_label: currentPeriod,
  gross: '',
  paid_at: '',
  method: 'bank_transfer',
  note: '',
})

const methodOptions = [
  { value: 'bank_transfer', label: 'Bank transfer' },
  { value: 'duitnow', label: 'DuitNow' },
  { value: 'cash', label: 'Cash' },
  { value: 'other', label: 'Other' },
]
const methodLabels: Record<string, string> = Object.fromEntries(methodOptions.map(o => [o.value, o.label]))

const teammateItems = computed(() => [
  { label: 'All teammates', value: '' },
  ...teammates.value.map(u => ({ label: `${u.name} (${u.role})`, value: String(u.id) })),
])
const formTeammateItems = computed(() => [
  { label: '— Select teammate —', value: '' },
  ...teammates.value.map(u => ({ label: `${u.name} (${u.role})`, value: String(u.id) })),
])

async function fetchEntries() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams()
    if (filters.user_id) params.set('user_id', filters.user_id)
    params.set('page', String(filters.page))

    const res = await apiFetch<{ data: PayrollEntry[], meta: any }>(`/api/v1/admin/payroll?${params}`)
    entries.value = res.data
    meta.value = res.meta
  }
  catch (e: any) {
    if (e?.status === 403) forbidden.value = true
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

onMounted(() => {
  fetchEntries()
  fetchTeammates()
})

watch(() => filters.user_id, () => {
  if (filters.page !== 1) filters.page = 1
  else fetchEntries()
})
watch(() => filters.page, () => fetchEntries())

async function record() {
  if (!form.user_id) {
    toast.error('Pick a teammate', 'Every payslip belongs to someone.')
    return
  }
  if (!(Number(form.gross) > 0)) {
    toast.error('Enter a gross amount', 'The amount must be greater than zero.')
    return
  }
  saving.value = true
  try {
    const body: Record<string, unknown> = {
      user_id: Number(form.user_id),
      period_label: form.period_label,
      gross_myr: Math.round(Number(form.gross)),
      method: form.method,
    }
    if (form.paid_at) body.paid_at = form.paid_at
    if (form.note) body.note = form.note
    await apiFetch('/api/v1/admin/payroll', { method: 'POST', body })
    toast.success('Payslip recorded', `${form.period_label} entry added to the ledger.`)
    form.user_id = ''
    form.gross = ''
    form.paid_at = ''
    form.note = ''
    form.period_label = currentPeriod
    showForm.value = false
    filters.page = 1
    fetchEntries()
  }
  catch {
    toast.error('Couldn’t record the payslip', 'Please try again.')
  }
  finally {
    saving.value = false
  }
}

function fmtDate(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}
// Whole-ringgit ledger (record-only) — no cents.
function fmtMyr(amount: number) {
  return `RM ${Number(amount).toLocaleString('en-MY')}`
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Payroll</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">Record-only ledger — amounts as agreed, no statutory calculation. Everyone reads their own payslips on /team.</p>
      </div>
      <button v-if="!forbidden" type="button" class="btn-pill btn-pill-primary text-[13px]" @click="showForm = !showForm">
        <UIcon :name="showForm ? 'i-lucide-x' : 'i-lucide-plus'" class="size-4" />
        {{ showForm ? 'Close' : 'Record payslip' }}
      </button>
    </div>

    <!-- Founder-only surface: a partner reaches the route but the gate stops the data. -->
    <div v-if="forbidden" class="rounded-2xl border p-12 text-center"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <UIcon name="i-lucide-lock" class="size-8 mb-3 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
      <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">Founder only</p>
      <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">
        The full payroll ledger is restricted. Your own payslips are on the
        <NuxtLink to="/team/payslips" class="underline" :style="{ color: 'var(--color-accent)' }">team workspace</NuxtLink>.
      </p>
    </div>

    <template v-else>
      <!-- Record form -->
      <div v-if="showForm" class="rounded-2xl border p-6 space-y-5 mb-8"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <div class="grid sm:grid-cols-2 gap-3">
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Teammate</span>
            <AdminSelect v-model="form.user_id" class="mt-1" :items="formTeammateItems" />
          </label>
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Period</span>
            <input v-model="form.period_label" type="text" placeholder="e.g. Jun 2026" class="contact-input mt-1 w-full">
          </label>
        </div>

        <div class="grid sm:grid-cols-2 gap-3">
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Gross (RM)</span>
            <input v-model="form.gross" type="number" min="0" step="1" placeholder="0" class="contact-input mt-1 w-full">
          </label>
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Paid on</span>
            <input v-model="form.paid_at" type="date" class="contact-input mt-1 w-full">
          </label>
        </div>

        <div>
          <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Method</span>
          <div class="flex flex-wrap gap-1.5 mt-1.5">
            <button v-for="m in methodOptions" :key="m.value" type="button" class="standard-pill"
              :style="form.method === m.value ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' } : {}"
              @click="form.method = m.value">{{ m.label }}</button>
          </div>
        </div>

        <label class="block">
          <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Note (optional)</span>
          <input v-model="form.note" type="text" placeholder="Internal note" class="contact-input mt-1 w-full">
        </label>

        <p class="text-[11px]" style="color: var(--color-text-tertiary);">Record-only: the gross is entered as agreed. EPF/SOCSO/EIS/PCB stay with the payroll provider, never computed here.</p>

        <button type="button" class="btn-pill btn-pill-primary w-full justify-center text-[13px]"
          :class="{ 'opacity-50': saving }" :disabled="saving" @click="record">
          {{ saving ? 'Recording…' : 'Record payslip' }}
        </button>
      </div>

      <!-- Filter -->
      <div class="flex flex-wrap items-center gap-3 mb-6">
        <AdminSelect v-model="filters.user_id" :items="teammateItems" class="w-56" />
        <span v-if="meta" class="ml-auto text-[12px]" style="color: var(--color-text-tertiary);">{{ meta.total }} entries</span>
      </div>

      <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

      <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading payroll…</div>

      <div v-else-if="!entries.length" class="rounded-2xl border p-12 text-center"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
        <UIcon name="i-lucide-banknote" class="size-8 mb-3 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
        <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">No payslips yet</p>
        <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">Record the first one with the button above.</p>
      </div>

      <!-- Desktop: table -->
      <div v-else class="hidden md:block admin-table-card">
        <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead>
            <tr>
              <th v-for="h in ['Teammate', 'Period', 'Gross', 'Paid on', 'Method', 'Recorded by']" :key="h"
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
              <td class="px-4 py-3.5">
                <span class="text-[13px] font-semibold tabular-nums" style="color: var(--color-text);">{{ fmtMyr(e.gross_myr) }}</span>
              </td>
              <td class="px-4 py-3.5 text-[12px]" style="color: var(--color-text-secondary);">{{ fmtDate(e.paid_at) }}</td>
              <td class="px-4 py-3.5 text-[13px]" style="color: var(--color-text);">{{ e.method ? (methodLabels[e.method] ?? e.method) : '—' }}</td>
              <td class="px-4 py-3.5 text-[12px]" style="color: var(--color-text-secondary);">{{ e.created_by_name ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>

      <!-- Mobile: cards -->
      <div v-if="entries.length && !loading" class="md:hidden space-y-2.5">
        <div v-for="e in entries" :key="e.id" class="rounded-xl border p-4"
          :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
          <div class="flex items-start justify-between gap-3 mb-1">
            <span class="text-[13px] font-semibold leading-tight" :style="{ color: 'var(--color-text)' }">{{ e.user_name ?? '—' }}</span>
            <span class="text-[14px] font-semibold tabular-nums" :style="{ color: 'var(--color-text)' }">{{ fmtMyr(e.gross_myr) }}</span>
          </div>
          <p class="text-[13px]" :style="{ color: 'var(--color-text-secondary)' }">{{ e.period_label }}</p>
          <div class="flex items-center justify-between gap-3 pt-2 mt-2 border-t" :style="{ borderColor: 'var(--color-border)' }">
            <span class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ e.method ? (methodLabels[e.method] ?? e.method) : '—' }}</span>
            <span class="text-[11px]" :style="{ color: 'var(--color-text-secondary)' }">{{ fmtDate(e.paid_at) }}</span>
          </div>
        </div>
      </div>

      <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
        <button :disabled="filters.page <= 1" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page--">← Prev</button>
        <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ filters.page }} / {{ meta.last_page }}</span>
        <button :disabled="filters.page >= meta.last_page" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page++">Next →</button>
      </div>
    </template>
  </div>
</template>
