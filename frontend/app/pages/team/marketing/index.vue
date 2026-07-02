<script setup lang="ts">
definePageMeta({ layout: 'team', middleware: 'team-auth' })
useHead({ title: 'Marketing — Team' })

const { apiFetch } = useTeamAuth()
const toast = useAdminToast()

interface Expense {
  id: number
  category: string
  amount_myr: number
  spent_at: string
  note: string | null
  created_at: string
}

const expenses = ref<Expense[]>([])
const meta = ref<{ current_page: number, last_page: number, total: number } | null>(null)
const totalMyr = ref<number | null>(null)
const loading = ref(true)
const error = ref('')

const page = ref(1)

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
    const res = await apiFetch<{ data: Expense[], meta: any, totals?: { amount_myr: number } }>(`/api/v1/team/marketing-expenses?page=${page.value}`)
    expenses.value = res.data
    meta.value = res.meta
    totalMyr.value = res.totals?.amount_myr ?? null
  }
  catch {
    error.value = 'Failed to load your spend entries. Check your session.'
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchExpenses)
watch(page, () => fetchExpenses())

async function record() {
  if (!form.category.trim()) {
    toast.error('Enter a category', 'e.g. Meta ads, content, tools.')
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
    await apiFetch('/api/v1/team/marketing-expenses', { method: 'POST', body })
    toast.success('Spend recorded', 'Added to your entries.')
    form.category = ''
    form.amount = ''
    form.note = ''
    showForm.value = false
    page.value = 1
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
  <div class="max-w-4xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Marketing</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">Your spend entries — you see only what you entered.</p>
      </div>
      <button type="button" class="btn-pill btn-pill-primary text-[13px]" @click="showForm = !showForm">
        <UIcon :name="showForm ? 'i-lucide-x' : 'i-lucide-plus'" class="size-4" />
        {{ showForm ? 'Close' : 'Record spend' }}
      </button>
    </div>

    <!-- Record form -->
    <div v-if="showForm" class="rounded-2xl border p-6 space-y-5 mb-8"
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

      <button type="button" class="btn-pill btn-pill-primary w-full justify-center text-[13px]"
        :class="{ 'opacity-50': saving }" :disabled="saving" @click="record">
        {{ saving ? 'Recording…' : 'Record spend' }}
      </button>
    </div>

    <div v-if="totalMyr !== null && expenses.length" class="flex items-center justify-end mb-4">
      <p class="text-[13px]" style="color: var(--color-text-secondary);">
        Your total: <span class="font-semibold tabular-nums" style="color: var(--color-text);">{{ fmtMyr(totalMyr) }}</span>
      </p>
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading spend…</div>

    <div v-else-if="!expenses.length" class="rounded-2xl border p-12 text-center"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <UIcon name="i-lucide-megaphone" class="size-8 mb-3 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
      <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">No spend recorded yet</p>
      <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">Record your first expense with the button above.</p>
    </div>

    <!-- Statement-style rows: category + note left, amount + date right. -->
    <div v-else class="rounded-2xl border divide-y"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <div v-for="x in expenses" :key="x.id" class="flex items-center justify-between gap-4 px-5 py-4"
        :style="{ borderColor: 'var(--color-border)' }">
        <div class="min-w-0">
          <p class="text-[13px] font-medium" :style="{ color: 'var(--color-text)' }">{{ x.category }}</p>
          <p class="text-[11px] truncate" :style="{ color: 'var(--color-text-tertiary)' }">
            {{ fmtDate(x.spent_at) }}<template v-if="x.note"> · {{ x.note }}</template>
          </p>
        </div>
        <span class="text-[14px] font-semibold tabular-nums shrink-0" :style="{ color: 'var(--color-text)' }">{{ fmtMyr(x.amount_myr) }}</span>
      </div>
    </div>

    <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
      <button :disabled="page <= 1" class="btn-pill btn-pill-ghost text-[12px]" @click="page--">← Prev</button>
      <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ page }} / {{ meta.last_page }}</span>
      <button :disabled="page >= meta.last_page" class="btn-pill btn-pill-ghost text-[12px]" @click="page++">Next →</button>
    </div>
  </div>
</template>
