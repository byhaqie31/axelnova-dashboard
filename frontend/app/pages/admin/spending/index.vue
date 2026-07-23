<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

interface Expense {
  id: number
  category: string
  amount_myr: number
  spent_at: string
  note: string | null
  entered_by: number
  entered_by_name: string | null
  created_at: string
}

const expenses = ref<Expense[]>([])
const meta = ref<{ current_page: number, last_page: number, total: number } | null>(null)
const totalMyr = ref<number | null>(null)
const loading = ref(true)
const error = ref('')

const filters = reactive({ category: '', page: 1 })

const showForm = ref(false)
const saving = ref(false)
const form = reactive({
  category: '',
  amount: '',
  spent_at: new Date().toISOString().slice(0, 10),
  note: '',
})

async function fetchExpenses() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams()
    if (filters.category) params.set('category', filters.category)
    params.set('page', String(filters.page))

    const res = await apiFetch<{ data: Expense[], meta: any, totals?: { amount_myr: number } }>(`/api/v1/admin/expenses?${params}`)
    expenses.value = res.data
    meta.value = res.meta
    totalMyr.value = res.totals?.amount_myr ?? null
  }
  catch {
    error.value = 'Failed to load marketing spend. Check your session.'
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchExpenses)

let searchTimer: ReturnType<typeof setTimeout>
watch(() => filters.category, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { filters.page = 1; fetchExpenses() }, 400)
})
watch(() => filters.page, () => fetchExpenses())

async function record() {
  if (!form.category.trim()) {
    toast.error('Enter a category', 'e.g. Ads, tools, hosting, travel.')
    return
  }
  if (!(Number(form.amount) > 0)) {
    toast.error('Enter an amount', 'The spend amount must be greater than zero.')
    return
  }
  saving.value = true
  try {
    const body: Record<string, unknown> = {
      category: form.category.trim(),
      amount_myr: Math.round(Number(form.amount)),
      spent_at: form.spent_at,
    }
    if (form.note) body.note = form.note
    await apiFetch('/api/v1/admin/expenses', { method: 'POST', body })
    toast.success('Spend recorded', 'The roll-up is updated.')
    form.category = ''
    form.amount = ''
    form.note = ''
    showForm.value = false
    filters.page = 1
    fetchExpenses()
  }
  catch {
    toast.error('Couldn’t record the spend', 'Please try again.')
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
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Company Spending</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">The spend ledger — every company expense in one place, record-only.</p>
      </div>
      <button type="button" class="btn-pill btn-pill-primary text-[13px]" @click="showForm = !showForm">
        <UIcon :name="showForm ? 'i-lucide-x' : 'i-lucide-plus'" class="size-4" />
        {{ showForm ? 'Close' : 'Record spend' }}
      </button>
    </div>

    <!-- Record form -->
    <div
v-if="showForm" class="rounded-2xl border p-6 space-y-5 mb-8"
      :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
      <div class="grid sm:grid-cols-2 gap-3">
        <label class="block">
          <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Category</span>
          <input v-model="form.category" type="text" placeholder="e.g. Meta ads" class="contact-input mt-1 w-full">
        </label>
        <label class="block">
          <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Amount (RM)</span>
          <input v-model="form.amount" type="number" min="0" step="1" placeholder="0" class="contact-input mt-1 w-full">
        </label>
      </div>

      <div class="grid sm:grid-cols-2 gap-3">
        <label class="block">
          <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Spent on</span>
          <input v-model="form.spent_at" type="date" class="contact-input mt-1 w-full">
        </label>
        <label class="block">
          <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Note (optional)</span>
          <input v-model="form.note" type="text" placeholder="Internal note" class="contact-input mt-1 w-full">
        </label>
      </div>

      <button
type="button" class="btn-pill btn-pill-primary w-full justify-center text-[13px]"
        :class="{ 'opacity-50': saving }" :disabled="saving" @click="record">
        {{ saving ? 'Recording…' : 'Record spend' }}
      </button>
    </div>

    <!-- Filter + roll-up -->
    <div class="flex flex-wrap items-center gap-3 mb-6">
      <AdminExpandingSearch v-model="filters.category" placeholder="Filter by category…" />
      <p v-if="totalMyr !== null" class="ml-auto text-[13px]" style="color: var(--color-text-secondary);">
        Total<span v-if="filters.category"> ({{ filters.category }})</span>:
        <span class="font-semibold tabular-nums" style="color: var(--color-text);">{{ fmtMyr(totalMyr) }}</span>
      </p>
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading spend…</div>

    <div
v-else-if="!expenses.length" class="rounded-2xl border p-12 text-center"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <UIcon name="i-lucide-receipt" class="size-8 mb-3 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
      <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">No spend recorded yet</p>
      <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">Record the first expense with the button above.</p>
    </div>

    <!-- Desktop: table -->
    <div v-else class="hidden md:block admin-table-card">
      <div class="overflow-x-auto">
      <table class="w-full text-left">
        <thead>
          <tr>
            <th
v-for="h in ['Category', 'Amount', 'Spent on', 'Entered by', 'Recorded']" :key="h"
              class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
              {{ h }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="x in expenses" :key="x.id" class="admin-table-row" style="cursor: default;">
            <td class="px-4 py-3.5">
              <p class="text-[13px] font-medium" style="color: var(--color-text);">{{ x.category }}</p>
              <p v-if="x.note" class="text-[11px] truncate max-w-64" style="color: var(--color-text-tertiary);">{{ x.note }}</p>
            </td>
            <td class="px-4 py-3.5">
              <span class="text-[13px] font-semibold tabular-nums" style="color: var(--color-text);">{{ fmtMyr(x.amount_myr) }}</span>
            </td>
            <td class="px-4 py-3.5 text-[12px]" style="color: var(--color-text-secondary);">{{ fmtDate(x.spent_at) }}</td>
            <td class="px-4 py-3.5 text-[13px]" style="color: var(--color-text);">{{ x.entered_by_name ?? '—' }}</td>
            <td class="px-4 py-3.5 text-[12px]" style="color: var(--color-text-secondary);">{{ fmtDate(x.created_at) }}</td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>

    <!-- Mobile: cards -->
    <div v-if="expenses.length && !loading" class="md:hidden space-y-2.5">
      <div
v-for="x in expenses" :key="x.id" class="rounded-xl border p-4"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
        <div class="flex items-start justify-between gap-3 mb-1">
          <span class="text-[13px] font-semibold leading-tight" :style="{ color: 'var(--color-text)' }">{{ x.category }}</span>
          <span class="text-[14px] font-semibold tabular-nums" :style="{ color: 'var(--color-text)' }">{{ fmtMyr(x.amount_myr) }}</span>
        </div>
        <p v-if="x.note" class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">{{ x.note }}</p>
        <div class="flex items-center justify-between gap-3 pt-2 mt-2 border-t" :style="{ borderColor: 'var(--color-border)' }">
          <span class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ x.entered_by_name ?? '—' }}</span>
          <span class="text-[11px]" :style="{ color: 'var(--color-text-secondary)' }">{{ fmtDate(x.spent_at) }}</span>
        </div>
      </div>
    </div>

    <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
      <button :disabled="filters.page <= 1" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page--">← Prev</button>
      <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ filters.page }} / {{ meta.last_page }}</span>
      <button :disabled="filters.page >= meta.last_page" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page++">Next →</button>
    </div>
  </div>
</template>
