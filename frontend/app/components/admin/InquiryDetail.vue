<script setup lang="ts">
// One inquiry's detail screen — shared by Sales pipeline › Inquiries
// (/admin/inquiries/[id], quote + contact) and Partners › Collaborations
// (/admin/collaborations/enquiries/[id], origin partner / collaboration).
// Same table, split by origin; opened under the wrong section it redirects
// to the right one, so old links keep working (COLLABORATIONS.md §Admin).
const props = defineProps<{
  inquiryId: string | number
  section: 'sales' | 'collaborations'
}>()

const { apiFetch } = useAdminAuth()
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
  origin: 'quote' | 'contact' | 'partner' | 'collaboration'
  subject: string | null
  /** origin 'partner': the studio partner the client asked to hire through us. */
  collaboration_partner?: { id: number, slug: string, name: string } | null
  status: string
  quotation_id: number | null
  quotation_reference: string | null
  created_at: string
}

const inquiry = ref<Inquiry | null>(null)

// /collaborations leads: a client hiring a partner studio through us, or a studio applying to partner.
const originHeading = computed(() => {
  switch (inquiry.value?.origin) {
    case 'contact': return 'Contact form'
    case 'partner': return `Partner enquiry · Axel Nova × ${inquiry.value.collaboration_partner?.name ?? 'partner'}`
    case 'collaboration': return 'Partnership application'
    default: return 'Project'
  }
})
const isCollab = computed(() => inquiry.value?.origin === 'partner' || inquiry.value?.origin === 'collaboration')
// Back to wherever this lead lives: the partner's page, the applications tab, or the sales list.
const back = computed(() => {
  if (props.section === 'sales') return { to: '/admin/inquiries', label: 'All inquiries' }
  const partner = inquiry.value?.collaboration_partner
  if (inquiry.value?.origin === 'partner' && partner) return { to: `/admin/collaborations/${partner.id}`, label: partner.name }
  return { to: '/admin/collaborations?view=applications', label: 'Applications' }
})
const replySubject = computed(() => inquiry.value?.origin === 'collaboration' ? 'Re: partnering with Axel Nova' : 'Re: your project inquiry')

const loading = ref(true)
const error = ref('')
const statusLoading = ref(false)
const unlinking = ref(false)

useHead(() => ({
  title: inquiry.value ? `${inquiry.value.name} — Inquiry` : 'Inquiry — Admin',
}))

async function fetchInquiry() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: Inquiry }>(`/api/v1/admin/inquiries/${props.inquiryId}`)
    inquiry.value = res.data
    // Opened under the wrong section (an old email link, say) → the right one.
    const collab = isCollab.value
    if (props.section === 'sales' && collab) await navigateTo(`/admin/collaborations/enquiries/${res.data.id}`, { replace: true })
    else if (props.section === 'collaborations' && !collab) await navigateTo(`/admin/inquiries/${res.data.id}`, { replace: true })
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
    await apiFetch(`/api/v1/admin/inquiries/${inquiry.value.id}/status`, {
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

async function unlinkQuotation() {
  if (!inquiry.value) return
  unlinking.value = true
  try {
    await apiFetch(`/api/v1/admin/inquiries/${inquiry.value.id}/quotation`, { method: 'DELETE' })
    toast.success('Quotation unlinked', 'This inquiry is no longer linked to a quotation.')
    await fetchInquiry()
  }
  catch {
    toast.error('Couldn’t unlink quotation', 'Something went wrong. Please try again.')
  }
  finally {
    unlinking.value = false
  }
}

onMounted(fetchInquiry)

function fmtDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// 'quoted' is set by building a quotation — not a manual status button.
const statusOptions = ['new', 'reviewing', 'archived']
const statusLabels: Record<string, string> = { new: 'New', reviewing: 'Reviewing', archived: 'Archived' }
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-6 md:pt-10 pb-24 md:pb-32">

    <NuxtLink
      :to="back.to" class="inline-flex items-center gap-2 text-[13px] mb-8 max-md:mb-5 max-md:py-1.5 transition-opacity hover:opacity-70 max-md:active:opacity-60"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> {{ back.label }}
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
    <p v-else-if="error" style="color: var(--color-danger);">{{ error }}</p>

    <div v-else-if="inquiry" class="grid lg:grid-cols-[1fr_300px] gap-8 max-md:gap-5 items-start">

      <div class="space-y-6 max-md:space-y-4 max-md:min-w-0">

        <!-- Header -->
        <div
class="rounded-2xl border p-6 max-md:p-5"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <div class="flex items-start justify-between flex-wrap gap-4 max-md:gap-3 max-md:flex-nowrap mb-5">
            <div class="max-md:min-w-0">
              <p class="text-[22px] max-md:text-[20px] font-bold tracking-tight max-md:break-words" style="color: var(--color-text);">{{ inquiry.name }}</p>
              <p v-if="inquiry.company" class="text-[14px] mt-0.5" style="color: var(--color-text-secondary);">{{ inquiry.company }}</p>
            </div>
            <AdminStatusPill :status="inquiry.status" size="md" class="max-md:shrink-0" />
          </div>
          <div class="grid sm:grid-cols-3 gap-4 pt-4 border-t" style="border-color: var(--color-border);">
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Email</p>
              <a :href="`mailto:${inquiry.email}`" class="text-[13px] font-medium max-md:break-all" style="color: var(--color-accent);">{{ inquiry.email }}</a>
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
        <div
class="rounded-2xl border p-6 max-md:p-5"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">
            {{ originHeading }}
          </p>
          <!-- Contact messages and partnership applications carry a subject, not project hints -->
          <div v-if="inquiry.origin === 'contact' || inquiry.origin === 'collaboration'">
            <p class="text-[11px] mb-0.5" style="color: var(--color-text-tertiary);">
              {{ inquiry.origin === 'collaboration' ? 'Describes themselves as' : 'Subject' }}
            </p>
            <p class="text-[13px]" style="color: var(--color-text);">{{ inquiry.subject ?? '—' }}</p>
          </div>
          <div v-else class="grid sm:grid-cols-3 gap-4">
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
        <div
class="rounded-2xl border p-6 max-md:p-5"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Details</p>
          <p class="text-[14px] leading-relaxed whitespace-pre-line max-md:break-words" style="color: var(--color-text);">{{ inquiry.message }}</p>
        </div>

      </div>

      <!-- Sidebar -->
      <div class="lg:sticky lg:top-20 space-y-4">

        <!-- Quotation: linked, or a choice of build-new / link-existing. Not for
             partnership applications — a studio applying to partner isn't a sale. -->
        <div
v-if="inquiry.origin !== 'collaboration'" class="rounded-2xl border p-5 space-y-3"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Quotation</p>

          <!-- Linked -->
          <template v-if="inquiry.quotation_id">
            <div
class="rounded-xl border px-4 py-3"
              :style="{ background: 'var(--color-bg-secondary)', borderColor: 'var(--color-border)' }">
              <p class="text-[11px]" style="color: var(--color-text-tertiary);">Linked quotation</p>
              <NuxtLink :to="`/admin/quotations/${inquiry.quotation_id}`" class="text-[13px] font-mono font-medium" style="color: var(--color-accent);">
                {{ inquiry.quotation_reference ?? `#${inquiry.quotation_id}` }}
              </NuxtLink>
            </div>
            <button
type="button"
              class="btn-pill btn-pill-ghost w-full justify-center text-[13px]"
              :class="{ 'opacity-50': unlinking }"
              :disabled="unlinking"
              @click="unlinkQuotation">
              {{ unlinking ? 'Unlinking…' : 'Unlink' }}
            </button>
          </template>

          <!-- Not linked: build a new one, or link one that already exists -->
          <template v-else>
            <NuxtLink
:to="`/admin/quotations/new?inquiry=${inquiry.id}`"
              class="btn-pill btn-pill-accent w-full justify-center text-[13px]">
              Build new quotation →
            </NuxtLink>
            <AdminLinkQuotationModal :inquiry-id="inquiry.id" @linked="fetchInquiry" />
            <p class="text-[11px]" style="color: var(--color-text-tertiary);">
              Build a fresh quote pre-filled with this client's details, or link one that already exists.
            </p>
          </template>
        </div>

        <!-- Status -->
        <div
class="rounded-2xl border p-5"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Update status</p>
          <div class="flex flex-wrap gap-2">
            <button
v-for="s in statusOptions" :key="s" type="button"
              class="status-pill status-pill-button max-md:text-[12px]! max-md:px-3! max-md:py-1.5!"
              :class="{ 'opacity-50': statusLoading }"
              :data-status="inquiry.status === s ? s : ''"
              :data-active="inquiry.status === s"
              :disabled="statusLoading || inquiry.status === s || inquiry.status === 'quoted'"
              @click="updateStatus(s)">
              {{ statusLabels[s] }}
            </button>
          </div>
          <p v-if="inquiry.status === 'quoted'" class="text-[11px] mt-3" style="color: var(--color-text-tertiary);">
            Quoted — a quotation is linked to this inquiry.
          </p>
        </div>

        <!-- Actions -->
        <div
class="rounded-2xl border p-5 space-y-3"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Actions</p>

          <a
:href="`mailto:${inquiry.email}?subject=${encodeURIComponent(replySubject)}`"
            class="btn-pill btn-pill-ghost w-full justify-center text-[13px]">
            Reply by email
          </a>

          <a
v-if="inquiry.phone"
            :href="`https://wa.me/${inquiry.phone.replace(/\D/g, '')}?text=Hi%20${encodeURIComponent(inquiry.name)}%2C%20thanks%20for%20your%20project%20inquiry.`"
            target="_blank" rel="noopener"
            class="btn-pill btn-pill-success w-full justify-center text-[13px]">
            WhatsApp
          </a>
        </div>

        <!-- Audit -->
        <div
class="rounded-xl border px-4 py-3.5 space-y-2"
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

        <AdminEmailHistory related-type="inquiry" :related-id="inquiry.id" />
      </div>
    </div>
  </div>
</template>
