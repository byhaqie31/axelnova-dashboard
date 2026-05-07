<script setup lang="ts">
import BrandMark from '~/components/shared/BrandMark.vue'
import { adminNav, isAdminNavActive } from '~/data/adminNav'

const sidebarCollapsed = ref(false)
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
          <BrandMark variant="compact" />
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
        class="hidden md:flex flex-col border-r transition-[width] duration-200"
        :style="{
          width: sidebarCollapsed ? '64px' : '240px',
          background: 'var(--color-bg)',
          borderColor: 'var(--color-border)',
        }"
      >
        <div class="p-3">
          <button
            class="w-full inline-flex items-center justify-center h-8 rounded-md transition-colors hover:bg-(--color-bg-secondary)"
            :style="{ color: 'var(--color-text-secondary)' }"
            :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
            @click="sidebarCollapsed = !sidebarCollapsed"
          >
            <UIcon
              :name="sidebarCollapsed ? 'i-fluent-panel-left-expand-24-regular' : 'i-fluent-panel-left-contract-24-regular'"
              class="size-4"
            />
          </button>
        </div>
        <nav class="px-3 pb-4 flex flex-col gap-1">
          <NuxtLink
            v-for="item in adminNav"
            :key="item.to"
            :to="item.to"
            class="group inline-flex items-center gap-3 h-9 rounded-md px-2.5 transition-colors"
            :class="sidebarCollapsed ? 'justify-center' : ''"
            :style="{
              background: isAdminNavActive(item, route.path) ? 'var(--color-accent-soft)' : 'transparent',
              color: isAdminNavActive(item, route.path) ? 'var(--color-accent)' : 'var(--color-text-secondary)',
            }"
            :title="sidebarCollapsed ? item.label : undefined"
          >
            <UIcon :name="item.icon" class="size-4 shrink-0" />
            <span v-if="!sidebarCollapsed" class="text-[13px] font-medium tracking-tight">{{ item.label }}</span>
          </NuxtLink>
        </nav>
      </aside>

      <!-- Sidebar (mobile drawer) -->
      <Transition name="page">
        <aside
          v-if="mobileNavOpen"
          class="md:hidden fixed inset-y-14 left-0 w-64 z-30 border-r"
          :style="{
            background: 'var(--color-bg)',
            borderColor: 'var(--color-border)',
          }"
        >
          <nav class="p-3 flex flex-col gap-1">
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
            <button
              class="mt-2 inline-flex items-center gap-3 h-10 rounded-md px-3 transition-colors hover:bg-(--color-bg-secondary)"
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
