<script setup lang="ts">
import { visibleTeamNav, type Role } from '~/data/teamNav'

definePageMeta({ layout: 'team', middleware: 'team-auth' })

const { apiFetch } = useTeamAuth()

interface Me { id: number, name: string, email: string, role?: Role }
const me = ref<Me | null>(null)

onMounted(async () => {
  try {
    me.value = await apiFetch<Me>('/api/v1/team/me')
  }
  catch {
    // Middleware handles hard auth failures; a soft miss just hides the greeting.
  }
})

const firstName = computed(() => me.value?.name?.split(' ')[0] ?? '')

// Surface the same role-filtered destinations as the sidebar, minus the Dashboard
// self-link, as entry cards — one obvious next step per surface the user can reach.
const shortcuts = computed(() =>
  visibleTeamNav(me.value?.role)
    .flatMap(group => group.items)
    .filter(item => item.to !== '/team'),
)

const blurbs: Record<string, string> = {
  '/team/inquiries': 'Triage new project inquiries and respond to prospects.',
  '/team/referrals': 'Work the referral programme — qualify and update leads.',
  '/team/payslips': 'Your payslips. Arriving with the payroll ledger (Phase 5).',
}
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <div class="mb-9">
      <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">
        {{ firstName ? `Welcome, ${firstName}` : 'Team workspace' }}
      </h1>
      <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
        Your workspace for inquiry triage and the referral programme.
      </p>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
      <NuxtLink
        v-for="item in shortcuts"
        :key="item.to"
        :to="item.to"
        class="group rounded-2xl border p-5 transition-colors hover:bg-(--color-bg-secondary)"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
      >
        <div class="flex items-center gap-3 mb-2.5">
          <span
            class="size-10 rounded-xl inline-flex items-center justify-center shrink-0"
            :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
          >
            <UIcon :name="item.icon" class="size-5" />
          </span>
          <span class="text-[15px] font-semibold tracking-tight" style="color: var(--color-text);">{{ item.label }}</span>
          <UIcon
            name="i-lucide-arrow-right"
            class="size-4 ml-auto transition-transform group-hover:translate-x-0.5"
            :style="{ color: 'var(--color-text-tertiary)' }"
          />
        </div>
        <p class="text-[13px] leading-relaxed" style="color: var(--color-text-secondary);">
          {{ blurbs[item.to] ?? '' }}
        </p>
      </NuxtLink>
    </div>
  </div>
</template>
