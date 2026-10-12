<script setup lang="ts">
// Partners › Collaborations — the studios Axel Nova builds with (the public
// /collaborations pages), NOT the referral programme (Referrals). Two views:
// the partners themselves, and studios applying to partner (inquiries with
// origin 'collaboration'). See docs/global/COLLABORATIONS.md §Admin.
import StatusPill from '~/components/shared/primitives/StatusPill.vue'
import type { AdminCollaborationPartnerRow } from '~/data/collaborations'
import { partnerSinceLabel } from '~/utils/collabAdmin'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })
useHead({ title: 'Collaborations — Admin' })

const { apiFetch } = useAdminAuth()
const route = useRoute()
const router = useRouter()
const isDesktop = useMediaQuery('(min-width: 768px)')

// ── View tabs — ?view= in the URL (router.replace so tab clicks don't stack
// history), same pattern as the Referrals hub.
type ViewTab = 'partners' | 'applications'
const VIEW_TABS: { value: ViewTab, label: string, icon: string }[] = [
  { value: 'partners', label: 'Partners', icon: 'i-lucide-combine' },
  { value: 'applications', label: 'Applications', icon: 'i-lucide-mail-plus' },
]
const activeView = computed<ViewTab>(() => (route.query.view === 'applications' ? 'applications' : 'partners'))
function setView(view: ViewTab) {
  if (activeView.value !== view) router.replace({ query: { ...route.query, view } })
}

const partners = ref<AdminCollaborationPartnerRow[]>([])
const applicationsNew = ref(0)
// Starts true — the fetch only runs in onMounted, so false would flash the empty state.
const loading = ref(true)
const error = ref('')

async function fetchPartners() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: AdminCollaborationPartnerRow[], applications_new: number }>('/api/v1/admin/collaboration-partners')
    partners.value = res.data
    applicationsNew.value = res.applications_new
  }
  catch {
    error.value = 'Failed to load collaboration partners. Check your session.'
  }
  finally {
    loading.value = false
  }
}

// Always loaded — it also carries the Applications badge.
onMounted(fetchPartners)

function openPartner(p: AdminCollaborationPartnerRow) {
  navigateTo(`/admin/collaborations/${p.id}`)
}

function enquiriesLabel(p: AdminCollaborationPartnerRow): string {
  if (!p.enquiries_open) return 'None open'
  return p.enquiries_new ? `${p.enquiries_open} open · ${p.enquiries_new} new` : `${p.enquiries_open} open`
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-6 md:pt-10 pb-24 md:pb-32">

    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-[24px] md:text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Collaborations</h1>
      <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
        <template v-if="activeView === 'partners'">
          Studios we build with on <a href="/collaborations" target="_blank" rel="noopener" class="underline" style="color: var(--color-accent);">/collaborations</a>
          — their profile, shared projects and the clients who asked to hire them through us. Not the referral programme.
        </template>
        <template v-else>
          Studios that applied through “Partner with us”. Reply from the enquiry; adding a new partner is still a data migration.
        </template>
      </p>
    </div>

    <!-- Tabs -->
    <div class="mb-6">
      <div class="tab-track" role="tablist" aria-label="Collaborations view">
        <button
          v-for="tab in VIEW_TABS"
          :id="`tab-${tab.value}`"
          :key="tab.value"
          type="button"
          role="tab"
          :aria-selected="activeView === tab.value"
          :aria-controls="`panel-${tab.value}`"
          class="tab-pill"
          @click="setView(tab.value)"
        >
          <UIcon :name="tab.icon" class="size-4" />
          {{ tab.label }}
          <span
            v-if="tab.value === 'applications' && applicationsNew"
            class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-[11px] font-semibold tabular-nums"
            style="background: var(--color-accent-soft); color: var(--color-accent);"
            :aria-label="`${applicationsNew} new`">
            {{ applicationsNew }}
          </span>
        </button>
      </div>
    </div>

    <!-- Partners -->
    <div v-if="activeView === 'partners'" id="panel-partners" role="tabpanel" aria-labelledby="tab-partners">
      <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

      <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading partners…</div>

      <div v-else-if="!partners.length && !error" class="text-center py-16" style="color: var(--color-text-secondary);">
        No collaboration partners yet.
      </div>

      <!-- Desktop: table -->
      <div v-else-if="isDesktop" class="admin-table-card">
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead>
              <tr>
                <th
                  v-for="h in ['Partner', 'Status', 'Partner since', 'Shared projects', 'Enquiries']" :key="h"
                  class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
                  {{ h }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in partners" :key="p.id" class="admin-table-row" @click="openPartner(p)">
                <td class="px-4 py-3.5">
                  <div class="flex items-center gap-3 min-w-0">
                    <span class="collab-logo-plate size-9 rounded-lg shrink-0 inline-flex items-center justify-center overflow-hidden">
                      <img v-if="p.symbol_url" :src="p.symbol_url" :alt="`${p.name} mark`" class="size-6 object-contain">
                      <UIcon v-else name="i-lucide-combine" class="size-4" style="color: var(--color-text-tertiary);" />
                    </span>
                    <div class="min-w-0">
                      <p class="text-[13px] font-medium truncate" style="color: var(--color-text);">{{ p.name }}</p>
                      <p class="text-[11px] truncate" style="color: var(--color-text-tertiary);">{{ p.tagline ?? `/collaborations/${p.slug}` }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-3.5">
                  <StatusPill :status="p.active ? 'live' : 'hidden'" type="collab_partner" />
                </td>
                <td class="px-4 py-3.5 text-[12px]" style="color: var(--color-text-secondary);">{{ partnerSinceLabel(p.partner_since) }}</td>
                <td class="px-4 py-3.5 text-[13px] tabular-nums" style="color: var(--color-text-secondary);">
                  {{ p.projects_public }} public <span style="color: var(--color-text-tertiary);">/ {{ p.projects_total }}</span>
                </td>
                <td class="px-4 py-3.5 text-[13px]" :style="{ color: p.enquiries_new ? 'var(--color-accent)' : 'var(--color-text-secondary)' }">
                  {{ enquiriesLabel(p) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Mobile: cards -->
      <div v-else class="space-y-2.5">
        <button
          v-for="p in partners" :key="p.id" type="button"
          class="w-full text-left rounded-xl border p-4 transition-colors active:bg-(--color-bg-secondary)"
          :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
          @click="openPartner(p)">
          <div class="flex items-center justify-between gap-3 mb-3">
            <div class="flex items-center gap-3 min-w-0">
              <span class="collab-logo-plate size-9 rounded-lg shrink-0 inline-flex items-center justify-center overflow-hidden">
                <img v-if="p.symbol_url" :src="p.symbol_url" :alt="`${p.name} mark`" class="size-6 object-contain">
                <UIcon v-else name="i-lucide-combine" class="size-4" style="color: var(--color-text-tertiary);" />
              </span>
              <div class="min-w-0">
                <p class="text-[13px] font-semibold truncate" style="color: var(--color-text);">{{ p.name }}</p>
                <p class="text-[11px]" style="color: var(--color-text-tertiary);">{{ partnerSinceLabel(p.partner_since) }}</p>
              </div>
            </div>
            <StatusPill :status="p.active ? 'live' : 'hidden'" type="collab_partner" class="shrink-0" />
          </div>
          <div class="pt-2 border-t flex items-center justify-between gap-3 text-[12px]" style="border-color: var(--color-border);">
            <span style="color: var(--color-text-secondary);">{{ p.projects_public }}/{{ p.projects_total }} projects public</span>
            <span :style="{ color: p.enquiries_new ? 'var(--color-accent)' : 'var(--color-text-secondary)' }">{{ enquiriesLabel(p) }}</span>
          </div>
        </button>
      </div>
    </div>

    <!-- Applications — studios asking to partner (origin 'collaboration') -->
    <div v-else id="panel-applications" role="tabpanel" aria-labelledby="tab-applications">
      <AdminCollabEnquiries origin="collaboration" empty-text="No partnership applications yet." />
    </div>
  </div>
</template>
