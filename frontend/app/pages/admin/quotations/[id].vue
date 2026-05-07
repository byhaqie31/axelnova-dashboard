<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()

interface Quotation {
  id: number
  reference_code: string
  name: string
  email: string
  phone: string
  company: string | null
  package_key: string | null
  estimate_min_myr: string
  estimate_max_myr: string
  estimate_weeks: number
  status: string
  submitted_at: string
  viewed_at: string | null
  form_payload: Record<string, unknown>
  addons: { key: string; label: string; amount_myr: string }[]
}

const quotation = ref<Quotation | null>(null)
const loading = ref(true)
const error = ref('')
const statusLoading = ref(false)
const convertLoading = ref(false)
const actionMessage = ref('')

useHead(() => ({
  title: quotation.value ? `${quotation.value.reference_code} — Admin` : 'Quotation — Admin',
}))

async function fetchQuotation() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: Quotation }>(`/api/v1/admin/quotations/${route.params.id}`)
    quotation.value = res.data
  }
  catch {
    error.value = 'Failed to load quotation.'
  }
  finally {
    loading.value = false
  }
}

async function updateStatus(status: string) {
  if (!quotation.value) return
  statusLoading.value = true
  try {
    await apiFetch(`/api/v1/admin/quotations/${quotation.value.id}/status`, {
      method: 'POST',
      body: { status },
    })
    quotation.value.status = status
    actionMessage.value = `Status updated to "${status}".`
  }
  catch {
    actionMessage.value = 'Failed to update status.'
  }
  finally {
    statusLoading.value = false
  }
}

async function convertToOrder() {
  if (!quotation.value) return
  convertLoading.value = true
  try {
    await apiFetch(`/api/v1/admin/quotations/${quotation.value.id}/convert`, {
      method: 'POST',
    })
    actionMessage.value = 'Converted to an order. Redirecting…'
    setTimeout(() => navigateTo(`/admin/orders/${quotation.value!.id}`), 600)
  }
  catch {
    actionMessage.value = 'Failed to convert.'
  }
  finally {
    convertLoading.value = false
  }
}

onMounted(fetchQuotation)

function fmtMyr(amount: string | number) {
  const n = Number(amount)
  return n >= 1000 ? `RM ${(n / 1000).toFixed(0)}k` : `RM ${n.toLocaleString()}`
}

function fmtDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// Manual status updates exclude 'converted' — that's a one-way trip via the Convert button.
const statusOptions = ['new', 'viewed', 'contacted', 'rejected', 'spam']
const statusColors: Record<string, string> = {
  new: 'var(--color-accent)', viewed: '#A855F7', contacted: 'var(--color-success)',
  converted: '#22c55e', rejected: 'var(--color-danger)', spam: 'var(--color-text-tertiary)',
}

const scopeFields = computed(() => {
  if (!quotation.value?.form_payload) return []
  const p = quotation.value.form_payload
  const rows: { label: string; value: unknown }[] = []
  const skip = new Set(['package_key', 'modifiers', 'addon_keys', 'rush', 'breakdown'])
  for (const [k, v] of Object.entries(p)) {
    if (skip.has(k) || v === '' || v === null || (Array.isArray(v) && !v.length)) continue
    rows.push({ label: k.replace(/_/g, ' '), value: v })
  }
  return rows
})
</script>

<template>
  <div class="max-w-5xl mx-auto px-6 pt-10 pb-32">

    <NuxtLink to="/admin/quotations" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All quotations
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
    <p v-else-if="error" style="color: var(--color-danger);">{{ error }}</p>

    <div v-else-if="quotation" class="grid lg:grid-cols-[1fr_300px] gap-8 items-start">

      <div class="space-y-6">

        <div class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <div class="flex items-start justify-between flex-wrap gap-4 mb-5">
            <div>
              <p class="font-mono text-[20px] font-bold mb-1" style="color: var(--color-accent);">{{ quotation.reference_code }}</p>
              <p class="text-[22px] font-bold tracking-tight" style="color: var(--color-text);">{{ quotation.name }}</p>
              <p v-if="quotation.company" class="text-[14px] mt-0.5" style="color: var(--color-text-secondary);">{{ quotation.company }}</p>
            </div>
            <span class="text-[12px] font-semibold px-3 py-1.5 rounded-full"
              :style="{ color: statusColors[quotation.status], background: `${statusColors[quotation.status]}20` }">
              {{ quotation.status }}
            </span>
          </div>
          <div class="grid sm:grid-cols-3 gap-4 pt-4 border-t" style="border-color: var(--color-border);">
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Email</p>
              <a :href="`mailto:${quotation.email}`" class="text-[13px] font-medium" style="color: var(--color-accent);">{{ quotation.email }}</a>
            </div>
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Phone</p>
              <a :href="`tel:${quotation.phone}`" class="text-[13px] font-medium" style="color: var(--color-text);">{{ quotation.phone }}</a>
            </div>
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Submitted</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ fmtDate(quotation.submitted_at) }}</p>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Estimate</p>
          <p class="text-[30px] font-bold tracking-tight mb-1" style="color: var(--color-text);">
            {{ fmtMyr(quotation.estimate_min_myr) }} – {{ fmtMyr(quotation.estimate_max_myr) }}
          </p>
          <p class="text-[13px]" style="color: var(--color-text-secondary);">
            {{ quotation.estimate_weeks }} week{{ quotation.estimate_weeks !== 1 ? 's' : '' }} ·
            Package: <code class="font-mono" style="color: var(--color-text);">{{ quotation.package_key ?? '—' }}</code>
          </p>
        </div>

        <div v-if="quotation.addons?.length" class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Add-ons</p>
          <div class="space-y-2">
            <div v-for="addon in quotation.addons" :key="addon.key" class="flex justify-between items-center">
              <span class="text-[13px]" style="color: var(--color-text);">{{ addon.label }}</span>
              <span class="text-[13px] font-semibold" style="color: var(--color-text);">{{ fmtMyr(addon.amount_myr) }}</span>
            </div>
          </div>
        </div>

        <div v-if="scopeFields.length" class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Scope details</p>
          <div class="grid sm:grid-cols-2 gap-x-8 gap-y-3">
            <div v-for="row in scopeFields" :key="row.label">
              <p class="text-[11px] capitalize mb-0.5" style="color: var(--color-text-tertiary);">{{ row.label }}</p>
              <p class="text-[13px]" style="color: var(--color-text);">
                {{ Array.isArray(row.value) ? row.value.join(', ') : String(row.value) }}
              </p>
            </div>
          </div>
        </div>

      </div>

      <div class="lg:sticky lg:top-20 space-y-4">

        <div class="rounded-2xl border p-5"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Update status</p>
          <div class="flex flex-wrap gap-2">
            <button v-for="s in statusOptions" :key="s" type="button"
              class="text-[11px] px-3 py-1.5 rounded-full border transition-all"
              :class="{ 'opacity-50': statusLoading }"
              :style="{
                borderColor: quotation.status === s ? statusColors[s] : 'var(--color-border)',
                background: quotation.status === s ? `${statusColors[s]}20` : 'transparent',
                color: quotation.status === s ? statusColors[s] : 'var(--color-text-secondary)',
              }"
              :disabled="statusLoading || quotation.status === s"
              @click="updateStatus(s)">
              {{ s }}
            </button>
          </div>
        </div>

        <div class="rounded-2xl border p-5 space-y-3"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Actions</p>

          <a :href="`mailto:${quotation.email}?subject=Re: your quote ${quotation.reference_code}`"
            class="btn-pill btn-pill-ghost w-full justify-center text-[13px]">
            Reply by email
          </a>

          <a :href="`https://wa.me/${quotation.phone.replace(/\D/g, '')}?text=Hi%20${encodeURIComponent(quotation.name)}%2C%20I%27m%20reaching%20out%20about%20your%20quote%20${quotation.reference_code}.`"
            target="_blank" rel="noopener"
            class="btn-pill btn-pill-ghost w-full justify-center text-[13px]">
            WhatsApp
          </a>

          <button class="btn-pill btn-pill-accent w-full justify-center text-[13px]"
            :disabled="convertLoading"
            @click="convertToOrder">
            {{ convertLoading ? 'Converting…' : 'Convert to order' }}
          </button>
        </div>

        <p v-if="actionMessage" class="text-[12px] text-center px-3" style="color: var(--color-text-secondary);">
          {{ actionMessage }}
        </p>

        <div class="rounded-xl border px-4 py-3.5 space-y-2"
          :style="{ background: 'var(--color-bg-secondary)', borderColor: 'var(--color-border)' }">
          <div class="flex justify-between">
            <span class="text-[11px]" style="color: var(--color-text-tertiary);">Submitted</span>
            <span class="text-[11px]" style="color: var(--color-text-secondary);">{{ fmtDate(quotation.submitted_at) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-[11px]" style="color: var(--color-text-tertiary);">First viewed</span>
            <span class="text-[11px]" style="color: var(--color-text-secondary);">{{ fmtDate(quotation.viewed_at) }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
