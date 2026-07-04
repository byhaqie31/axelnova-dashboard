<script setup lang="ts">
// Home (Task 4 of the portal restructure) — repurposed from the old shortcut
// grid (inquiries/referrals entry cards) into the company announcements feed,
// now that the team no longer touches operational data. The announcements
// backend (Task 6) is wired below — GET /v1/team/announcements already
// filters to published + audience team|all, so this page just renders what
// it gets back, newest first.
definePageMeta({ layout: 'team', middleware: 'team-auth' })
useHead({ title: 'Home — Team' })

// Shared /v1/team/me state (composables/useTeamMe.ts) — same ref the team
// layout already fetched, so this just reads it (a cheap re-fetch here too
// keeps the greeting correct even on a hard refresh landing straight on /team).
const { me, refresh: fetchMe } = useTeamMe()
const { apiFetch } = useTeamAuth()

// Subset of backend App\Http\Resources\AnnouncementResource — only the
// fields this feed renders.
interface Announcement {
  id: number
  title: string
  body: string
  published_at: string
}

const announcements = ref<Announcement[]>([])
const loading = ref(true)

async function fetchAnnouncements() {
  loading.value = true
  try {
    const res = await apiFetch<{ data: Announcement[] }>('/api/v1/team/announcements')
    announcements.value = res.data
  }
  catch {
    // Feed failure degrades to the empty state — Home is a read-only, low-
    // stakes surface, so a toast/error banner would be more noise than help.
    announcements.value = []
  }
  finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchMe()
  fetchAnnouncements()
})

const firstName = computed(() => me.value?.name?.split(' ')[0] ?? '')

function fmtDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <!-- Compact welcome header -->
    <div class="mb-8">
      <h1 class="text-[24px] font-bold tracking-tight" style="color: var(--color-text);">
        {{ firstName ? `Welcome, ${firstName}` : 'Team Workspace' }}
      </h1>
      <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
        Company announcements and notices.
      </p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">
      Loading announcements…
    </div>

    <!-- Empty -->
    <div
      v-else-if="!announcements.length"
      class="rounded-2xl border px-6 py-14 text-center"
      :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
    >
      <span
        class="size-12 rounded-2xl inline-flex items-center justify-center mb-4"
        :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
      >
        <UIcon name="i-lucide-radio" class="size-6" />
      </span>
      <p class="text-[15px] font-semibold tracking-tight mb-1" style="color: var(--color-text);">No announcements yet</p>
      <p class="text-[13px] max-w-sm mx-auto leading-relaxed" style="color: var(--color-text-secondary);">
        Company-wide notices will show up here as soon as one is posted.
      </p>
    </div>

    <!-- Feed -->
    <div v-else class="flex flex-col gap-4">
      <article
        v-for="item in announcements"
        :key="item.id"
        class="rounded-2xl border p-5"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
      >
        <div class="flex items-start justify-between gap-3 mb-2">
          <h2 class="text-[15px] font-semibold tracking-tight" style="color: var(--color-text);">{{ item.title }}</h2>
          <span class="text-[11px] shrink-0 whitespace-nowrap" style="color: var(--color-text-tertiary);">{{ fmtDate(item.published_at) }}</span>
        </div>
        <p class="text-[13px] leading-relaxed whitespace-pre-line" style="color: var(--color-text-secondary);">{{ item.body }}</p>
      </article>
    </div>
  </div>
</template>
