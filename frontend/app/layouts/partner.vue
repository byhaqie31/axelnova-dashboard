<script setup lang="ts">
import BrandMark from '~/components/shared/BrandMark.vue'
import { isAdminNavActive, visiblePartnersNav } from '~/data/partnersNav'

// Partner-portal shell: topbar (brand + who's signed in + sign out) over a
// horizontal, type-filtered nav strip (Task 9 — the portal is type-aware:
// referrers see Referrals/Earnings, investors see Documents/Reports, everyone
// gets Dashboard/Profile). Reads the shared /v1/partner/me singleton
// (usePartnerMe) — the same ref the pages use — and renders the shared items
// only until it resolves, so the shell stays sane pre-login/pre-fetch.
const { logout } = usePartnerAuth()
const { me, refresh: fetchMe } = usePartnerMe()

const route = useRoute()

onMounted(() => {
  // Login/forgot use layout:false — anything under this layout is authed, so
  // the fetch is safe; failures are non-fatal (middleware handles hard 401s).
  fetchMe()
})

const navItems = computed(() => visiblePartnersNav(me.value?.type))

const firstName = computed(() => me.value?.profile?.name?.split(' ')[0] ?? '')
</script>

<template>
  <div class="min-h-screen flex flex-col" style="background: var(--color-bg-secondary); color: var(--color-text);">
    <header
      class="sticky top-0 z-40 border-b backdrop-blur-xl"
      :style="{ background: 'var(--nav-bg-scrolled)', borderColor: 'var(--color-border)' }"
    >
      <div class="max-w-5xl mx-auto px-5 sm:px-6 h-14 flex items-center justify-between gap-4">
        <div class="flex items-center gap-2.5">
          <BrandMark variant="mark-only" class="partner-brand" />
          <span class="text-[15px] font-semibold tracking-tight" style="color: var(--color-text);">Partner Portal</span>
        </div>

        <div class="flex items-center gap-3">
          <span v-if="firstName" class="hidden sm:inline text-[13px]" style="color: var(--color-text-secondary);">
            {{ firstName }}
          </span>
          <button type="button" class="partner-signout" @click="logout">
            <UIcon name="i-lucide-log-out" class="size-4" />
            <span class="hidden sm:inline">Sign out</span>
          </button>
        </div>
      </div>

      <!-- Type-filtered nav strip — scrolls horizontally on narrow screens. -->
      <nav class="max-w-5xl mx-auto px-5 sm:px-6 partner-nav-scroll" aria-label="Partner portal">
        <div class="flex items-center gap-1 -mb-px">
          <NuxtLink
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            class="partner-nav-item"
            :data-active="isAdminNavActive(item, route.path)"
          >
            <UIcon :name="item.icon" class="size-4 shrink-0" />
            <span>{{ item.label }}</span>
          </NuxtLink>
        </div>
      </nav>
    </header>

    <main class="flex-1 w-full max-w-5xl mx-auto px-5 sm:px-6 py-8 sm:py-10">
      <slot />
    </main>
  </div>
</template>

<style scoped>
.partner-brand :deep(img) {
  width: 1.9rem;
  height: 1.9rem;
}

.partner-signout {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 34px;
  padding: 0 12px;
  border-radius: 9999px;
  font-size: 13px;
  color: var(--color-text-secondary);
  transition: background 0.15s ease, color 0.15s ease;
}
.partner-signout:hover {
  background: var(--color-bg-secondary);
  color: var(--color-text);
}

/* Horizontal nav strip — underline marks the active destination. */
.partner-nav-scroll {
  overflow-x: auto;
  scrollbar-width: none;
}
.partner-nav-scroll::-webkit-scrollbar {
  display: none;
}

.partner-nav-item {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  height: 40px;
  padding: 0 12px;
  white-space: nowrap;
  font-size: 13px;
  font-weight: 500;
  color: var(--color-text-secondary);
  border-bottom: 2px solid transparent;
  transition: color 0.15s ease, border-color 0.15s ease;
}
.partner-nav-item:hover {
  color: var(--color-text);
}
.partner-nav-item[data-active="true"] {
  color: var(--color-accent);
  border-bottom-color: var(--color-accent);
}
</style>
