<script setup lang="ts">
import { adminNav, isAdminNavActive } from '~/data/adminNav'

const mobileNavOpen = ref(false)

const route = useRoute()
const { logout } = useAdminAuth()

watch(() => route.fullPath, () => { mobileNavOpen.value = false })
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
          <NuxtLink to="/admin" class="inline-flex items-center gap-2 font-semibold tracking-tight text-[13px]">
            <span class="aurora-orb size-7 rounded-full inline-flex items-center justify-center shrink-0">
              <img
                src="/axel_nova_favicon.png"
                alt=""
                aria-hidden="true"
                class="size-6.5 object-contain relative z-10"
              />
            </span>
            <span class="text-gradient">Admin Portal</span>
          </NuxtLink>
        </div>

        <div class="flex items-center gap-2">
          <button
            class="hidden md:inline-flex items-center gap-1.5 text-[12px] px-3 py-1.5 rounded-full border transition-colors hover:bg-(--color-bg-secondary)"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }"
            @click="logout"
          >
            <UIcon name="i-lucide-log-out" class="size-3.5" />
            Sign out
          </button>
          <UAvatar size="sm" text="Q" />
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
.aurora-orb {
  position: relative;
  background: color-mix(in srgb, var(--color-accent-soft) 45%, var(--color-bg-elevated));
  isolation: isolate;
}
.aurora-orb::before {
  content: '';
  position: absolute;
  inset: -3px;
  border-radius: inherit;
  background: var(--grad-iridescent);
  filter: blur(6px);
  opacity: 0.55;
  z-index: 0;
  animation: aurora-orb-pulse 4.5s ease-in-out infinite;
}
.aurora-orb::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background: color-mix(in srgb, var(--color-accent-soft) 45%, var(--color-bg-elevated));
  z-index: 0;
}

@keyframes aurora-orb-pulse {
  0%, 100% { opacity: 0.45; transform: scale(1); }
  50%      { opacity: 0.65; transform: scale(1.06); }
}

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
  .aurora-orb::before { animation: none; opacity: 0.5; }
  .drawer-backdrop-enter-active,
  .drawer-backdrop-leave-active,
  .drawer-panel-enter-active,
  .drawer-panel-leave-active { transition: none; }
}
</style>
