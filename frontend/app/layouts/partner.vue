<script setup lang="ts">
import BrandMark from '~/components/shared/BrandMark.vue'

// Minimal partner-portal shell: a single topbar (brand + who's signed in + sign out)
// over the content slot. No sidebar — the portal is intentionally read-mostly.
interface Me { id: number, name: string, email: string, code: string, commission_pct: number }

const { apiFetch, logout } = usePartnerAuth()
const me = ref<Me | null>(null)

async function fetchMe() {
  try {
    me.value = await apiFetch<Me>('/api/v1/partner/me')
  }
  catch {
    // Non-fatal — the partner-auth middleware bounces on hard auth failures.
  }
}

onMounted(fetchMe)

const firstName = computed(() => me.value?.name?.split(' ')[0] ?? '')
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
</style>
