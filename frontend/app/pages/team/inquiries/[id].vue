<script setup lang="ts">
definePageMeta({ layout: 'team', middleware: 'team-auth' })

const route = useRoute()
const { apiFetch } = useTeamAuth()
const toast = useAdminToast()

interface Inquiry {
  id: number
  name: string
  email: string
  phone: string | null
  company: string | null
  project_type: string | null
  budget_hint: string | null
  timeline_hint: string | null
  message: string
  source: string
  status: string
  created_at: string
}

const inquiry = ref<Inquiry | null>(null)
const loading = ref(true)
const error = ref('')
const statusLoading = ref(false)

useHead(() => ({
  title: inquiry.value ? `${inquiry.value.name} — Inquiry` : 'Inquiry — Team',
}))

async function fetchInquiry() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: Inquiry }>(`/api/v1/team/inquiries/${route.params.id}`)
    inquiry.value = res.data
  }
  catch {
    error.value = 'Failed to load inquiry.'
  }
  finally {
    loading.value = false
  }
}

async function updateStatus(status: string) {
  if (!inquiry.value) return
  statusLoading.value = true
  try {
    await apiFetch(`/api/v1/team/inquiries/${inquiry.value.id}/status`, {
      method: 'POST',
      body: { status },
    })
    inquiry.value.status = status
    toast.success('Status updated', `Inquiry set to ${statusLabels[status] ?? status}.`)
  }
  catch {
    toast.error('Couldn’t update status', 'Something went wrong. Please try again.')
  }
  finally {
    statusLoading.value = false
  }
}

onMounted(fetchInquiry)

function fmtDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// Triage set only — 'quoted' is a cockpit transition (building a quotation).
const statusOptions = ['new', 'reviewing', 'archived']
const statusLabels: Record<string, string> = { new: 'New', reviewing: 'Reviewing', archived: 'Archived' }
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <NuxtLink to="/team/inquiries" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All inquiries
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
    <p v-else-if="error" style="color: var(--color-danger);">{{ error }}</p>

    <div v-else-if="inquiry" class="grid lg:grid-cols-[1fr_300px] gap-8 items-start">

      <div class="space-y-6">

        <!-- Header -->
        <div class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <div class="flex items-start justify-between flex-wrap gap-4 mb-5">
            <div>
              <p class="text-[22px] font-bold tracking-tight" style="color: var(--color-text);">{{ inquiry.name }}</p>
              <p v-if="inquiry.company" class="text-[14px] mt-0.5" style="color: var(--color-text-secondary);">{{ inquiry.company }}</p>
            </div>
            <AdminStatusPill :status="inquiry.status" size="md" />
          </div>
          <div class="grid sm:grid-cols-3 gap-4 pt-4 border-t" style="border-color: var(--color-border);">
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Email</p>
              <a :href="`mailto:${inquiry.email}`" class="text-[13px] font-medium" style="color: var(--color-accent);">{{ inquiry.email }}</a>
            </div>
            <div v-if="inquiry.phone">
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Phone</p>
              <a :href="`tel:${inquiry.phone}`" class="text-[13px] font-medium" style="color: var(--color-text);">{{ inquiry.phone }}</a>
            </div>
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Submitted</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ fmtDate(inquiry.created_at) }}</p>
            </div>
          </div>
        </div>

        <!-- Project hints -->
        <div class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Project</p>
          <div class="grid sm:grid-cols-3 gap-4">
            <div>
              <p class="text-[11px] mb-0.5" style="color: var(--color-text-tertiary);">Type</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ inquiry.project_type ?? '—' }}</p>
            </div>
            <div>
              <p class="text-[11px] mb-0.5" style="color: var(--color-text-tertiary);">Budget</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ inquiry.budget_hint ?? '—' }}</p>
            </div>
            <div>
              <p class="text-[11px] mb-0.5" style="color: var(--color-text-tertiary);">Timeline</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ inquiry.timeline_hint ?? '—' }}</p>
            </div>
          </div>
        </div>

        <!-- Message -->
        <div class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Details</p>
          <p class="text-[14px] leading-relaxed whitespace-pre-line" style="color: var(--color-text);">{{ inquiry.message }}</p>
        </div>

      </div>

      <!-- Sidebar -->
      <div class="lg:sticky lg:top-20 space-y-4">

        <!-- Status -->
        <div class="rounded-2xl border p-5"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Update status</p>
          <div class="flex flex-wrap gap-2">
            <button v-for="s in statusOptions" :key="s" type="button"
              class="status-pill status-pill-button"
              :class="{ 'opacity-50': statusLoading }"
              :data-status="inquiry.status === s ? s : ''"
              :data-active="inquiry.status === s"
              :disabled="statusLoading || inquiry.status === s || inquiry.status === 'quoted'"
              @click="updateStatus(s)">
              {{ statusLabels[s] }}
            </button>
          </div>
          <p v-if="inquiry.status === 'quoted'" class="text-[11px] mt-3" style="color: var(--color-text-tertiary);">
            Quoted — a quotation was built for this inquiry in the cockpit.
          </p>
        </div>

        <!-- Actions -->
        <div class="rounded-2xl border p-5 space-y-3"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Respond</p>

          <a :href="`mailto:${inquiry.email}?subject=Re: your project inquiry`"
            class="btn-pill btn-pill-ghost w-full justify-center text-[13px]">
            Reply by email
          </a>

          <a v-if="inquiry.phone"
            :href="`https://wa.me/${inquiry.phone.replace(/\D/g, '')}?text=Hi%20${encodeURIComponent(inquiry.name)}%2C%20thanks%20for%20your%20project%20inquiry.`"
            target="_blank" rel="noopener"
            class="btn-pill btn-pill-success w-full justify-center text-[13px]">
            WhatsApp
          </a>
        </div>

        <!-- Audit -->
        <div class="rounded-xl border px-4 py-3.5 space-y-2"
          :style="{ background: 'var(--color-bg-secondary)', borderColor: 'var(--color-border)' }">
          <div class="flex justify-between">
            <span class="text-[11px]" style="color: var(--color-text-tertiary);">Submitted</span>
            <span class="text-[11px]" style="color: var(--color-text-secondary);">{{ fmtDate(inquiry.created_at) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-[11px]" style="color: var(--color-text-tertiary);">Source</span>
            <span class="text-[11px] capitalize" style="color: var(--color-text-secondary);">{{ inquiry.source }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
