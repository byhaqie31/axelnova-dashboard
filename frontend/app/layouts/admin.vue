<script setup lang="ts">
import BrandMark from '~/components/shared/BrandMark.vue'
import { adminNav, isAdminNavActive } from '~/data/adminNav'

const mobileNavOpen = ref(false)
const profileOpen = ref(false)

const route = useRoute()
const { logout, apiFetch } = useAdminAuth()

interface Me { id: number, name: string, email: string }
const me = ref<Me | null>(null)

async function fetchMe() {
  try {
    me.value = await apiFetch<Me>('/api/v1/admin/me')
  }
  catch {
    // Non-fatal — middleware will bounce to /admin/login on hard auth failures.
  }
}
onMounted(fetchMe)

watch(() => route.fullPath, () => {
  mobileNavOpen.value = false
  profileOpen.value = false
})

onKeyStroke('Escape', () => { if (profileOpen.value) profileOpen.value = false })

// One title for every page rendered under this layout.
// Per-page useHead calls deliberately don't set `title` so this stays.
useHead({ title: 'Admin Portal' })
</script>

<template>
  <div class="min-h-screen flex flex-col" style="background: var(--color-bg-secondary); color: var(--color-text);">
    <!-- Topbar -->
    <header
      class="sticky top-0 z-40 h-14 border-b backdrop-blur"
      :style="{
        background: 'var(--color-bg)',
        borderColor: 'var(--color-border)',
      }"
    >
      <div class="h-full px-4 md:px-6 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <button
            class="md:hidden inline-flex items-center justify-center size-8 rounded-md"
            :style="{ color: 'var(--color-text)' }"
            aria-label="Toggle navigation"
            @click="mobileNavOpen = !mobileNavOpen"
          >
            <UIcon :name="mobileNavOpen ? 'i-fluent-dismiss-24-regular' : 'i-fluent-line-horizontal-3-24-regular'" class="size-5" />
          </button>
          <BrandMark to="/admin" wordmark="Admin Portal" />
        </div>

        <div class="relative flex items-center">
          <button
            type="button"
            class="size-9 rounded-full inline-flex items-center justify-center border transition-colors hover:bg-(--color-bg-secondary)"
            :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)', color: 'var(--color-text-secondary)' }"
            :aria-expanded="profileOpen"
            aria-label="Account menu"
            @click="profileOpen = !profileOpen"
          >
            <UIcon name="i-lucide-user" class="size-4" />
          </button>

          <div v-if="profileOpen" class="fixed inset-0 z-40 cursor-default" @click="profileOpen = false" />

          <Transition name="dropdown-panel">
            <div
              v-if="profileOpen"
              class="absolute right-0 top-full mt-2 w-72 max-w-[calc(100vw-1.5rem)] z-50 rounded-xl border overflow-hidden"
              :style="{
                borderColor: 'var(--color-border)',
                background: 'var(--color-bg)',
                boxShadow: 'var(--shadow-lg)',
              }"
              role="menu"
            >
              <div class="flex flex-col items-center text-center px-4 pt-5 pb-4">
                <span
                  class="size-14 rounded-full inline-flex items-center justify-center mb-3"
                  :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
                >
                  <UIcon name="i-lucide-user" class="size-6" />
                </span>
                <p class="text-[14px] font-semibold tracking-tight" :style="{ color: 'var(--color-text)' }">
                  {{ me?.name ?? '—' }}
                </p>
                <p class="text-[12px] mt-0.5 break-all" :style="{ color: 'var(--color-text-tertiary)' }">
                  {{ me?.email ?? '' }}
                </p>
                <span
                  class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded inline-flex items-center gap-1 mt-2"
                  :style="{ color: 'var(--color-accent)', background: 'var(--color-accent-soft)' }"
                >
                  <UIcon name="i-lucide-star" class="size-3" />
                  Founder
                </span>
              </div>

              <div class="border-t" :style="{ borderColor: 'var(--color-border)' }">
                <button
                  type="button"
                  class="w-full inline-flex items-center justify-center gap-2 py-3 text-[13px] font-medium transition-colors hover:bg-(--color-bg-secondary)"
                  :style="{ color: 'var(--color-text-secondary)' }"
                  @click="logout"
                >
                  <UIcon name="i-lucide-log-out" class="size-4" />
                  Sign out
                </button>
              </div>
            </div>
          </Transition>
        </div>
      </div>
    </header>

    <div class="flex-1 flex">
      <!-- Sidebar (desktop) -->
      <aside
        class="hidden md:flex flex-col border-r"
        :style="{
          width: '240px',
          background: 'var(--color-bg)',
          borderColor: 'var(--color-border)',
        }"
      >
        <nav class="p-3 flex flex-col gap-1">
          <NuxtLink
            v-for="item in adminNav"
            :key="item.to"
            :to="item.to"
            class="inline-flex items-center gap-3 h-9 rounded-md px-2.5 transition-colors"
            :style="{
              background: isAdminNavActive(item, route.path) ? 'var(--color-accent-soft)' : 'transparent',
              color: isAdminNavActive(item, route.path) ? 'var(--color-accent)' : 'var(--color-text-secondary)',
            }"
          >
            <UIcon :name="item.icon" class="size-4 shrink-0" />
            <span class="text-[13px] font-medium tracking-tight">{{ item.label }}</span>
          </NuxtLink>
        </nav>
      </aside>

      <!-- Sidebar (mobile floating drawer + backdrop) -->
      <Transition name="drawer-backdrop">
        <button
          v-if="mobileNavOpen"
          class="md:hidden fixed inset-0 top-14 z-20 cursor-default"
          style="background: rgba(0, 0, 0, 0.32); backdrop-filter: blur(2px);"
          aria-label="Close navigation"
          @click="mobileNavOpen = false"
        />
      </Transition>
      <Transition name="drawer-panel">
        <aside
          v-if="mobileNavOpen"
          class="md:hidden fixed left-3 right-auto top-17 bottom-3 w-64 z-30 rounded-2xl border shadow-2xl overflow-hidden"
          :style="{
            background: 'var(--color-bg)',
            borderColor: 'var(--color-border)',
          }"
        >
          <nav class="p-3 flex flex-col gap-1 h-full overflow-y-auto">
            <NuxtLink
              v-for="item in adminNav"
              :key="item.to"
              :to="item.to"
              class="inline-flex items-center gap-3 h-10 rounded-md px-3 transition-colors"
              :style="{
                background: isAdminNavActive(item, route.path) ? 'var(--color-accent-soft)' : 'transparent',
                color: isAdminNavActive(item, route.path) ? 'var(--color-accent)' : 'var(--color-text-secondary)',
              }"
            >
              <UIcon :name="item.icon" class="size-4 shrink-0" />
              <span class="text-[13px] font-medium tracking-tight">{{ item.label }}</span>
            </NuxtLink>
            <hr class="my-2 border-0 border-t" :style="{ borderColor: 'var(--color-border)' }" />
            <button
              class="inline-flex items-center gap-3 h-10 rounded-md px-3 transition-colors hover:bg-(--color-bg-secondary)"
              :style="{ color: 'var(--color-text-secondary)' }"
              @click="logout"
            >
              <UIcon name="i-lucide-log-out" class="size-4 shrink-0" />
              <span class="text-[13px] font-medium tracking-tight">Sign out</span>
            </button>
          </nav>
        </aside>
      </Transition>

      <!-- Main -->
      <main class="flex-1 min-w-0">
        <slot />
      </main>
    </div>
  </div>
</template>

<style scoped>
/* Mobile floating drawer */
.drawer-backdrop-enter-active,
.drawer-backdrop-leave-active {
  transition: opacity 0.2s ease;
}
.drawer-backdrop-enter-from,
.drawer-backdrop-leave-to { opacity: 0; }

.drawer-panel-enter-active,
.drawer-panel-leave-active {
  transition: transform 0.25s cubic-bezier(0.32, 0.72, 0, 1), opacity 0.2s ease;
}
.drawer-panel-enter-from,
.drawer-panel-leave-to {
  opacity: 0;
  transform: translateX(-12px);
}

@media (prefers-reduced-motion: reduce) {
  .drawer-backdrop-enter-active,
  .drawer-backdrop-leave-active,
  .drawer-panel-enter-active,
  .drawer-panel-leave-active { transition: none; }
}
</style>
