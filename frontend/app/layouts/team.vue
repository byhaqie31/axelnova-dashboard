<script setup lang="ts">
import BrandMark from '~/components/shared/BrandMark.vue'
import { availabilityMeta as findAvailabilityMeta } from '~/data/availabilityStatuses'
import { visibleTeamNav, isAdminNavActive, type NavGroup, type Role } from '~/data/teamNav'

useSeoMeta({ robots: 'noindex, nofollow' })

const mobileNavOpen = ref(false)
const profileOpen = ref(false)

// Desktop sidebar collapse → icon-only rail. Cookie-backed so it's resolved during
// SSR and the rail doesn't flash from expanded → collapsed on reload. Separate key
// from the admin shell so the two surfaces remember their own state.
const sidebarCollapsed = useCookie<boolean>('axn_team_sidebar_collapsed', { default: () => false })

// Per-group open/closed state, cookie-backed (SSR-resolved, no open/closed flash).
const navGroupsOpen = useCookie<Record<string, boolean>>('axn_team_nav_groups', { default: () => ({}) })

const route = useRoute()
const { logout } = useTeamAuth()

// Light / dark toggle — flips the persisted colour-mode preference (same engine
// as the admin shell; @nuxt/ui applies the .dark class before paint).
const colorMode = useColorMode()
const toggleDark = () => { colorMode.preference = colorMode.value === 'dark' ? 'light' : 'dark' }

// Shared /v1/team/me state (composables/useTeamMe.ts) — the same ref the Home
// and Profile pages read/write, so saving a new availability status on
// /team/profile updates this header instantly, no reload needed.
const { me, refresh: fetchMe } = useTeamMe()

// Role-scoped nav: the base Workspace group shows to everyone; the Marketing
// group (marketing + analytics) is marketer/founder-only.
const navGroups = computed<NavGroup[]>(() => visibleTeamNav(me.value?.role as Role | undefined))

const roleLabel = computed(() => {
  const r = me.value?.role
  return r ? r.charAt(0).toUpperCase() + r.slice(1) : ''
})

// Availability status (Profile page, Task 4) — surfaced as a small dot on the
// avatar + a pill in the account dropdown so a teammate's status is visible
// without opening /team/profile. Shares its option list with the Profile
// page's pill picker (data/availabilityStatuses.ts).
const availabilityMeta = computed(() => findAvailabilityMeta(me.value?.availability))

function groupHasActive(group: NavGroup): boolean {
  return group.items.some(item => isAdminNavActive(item, route.path))
}
function isGroupOpen(group: NavGroup): boolean {
  return groupHasActive(group) || navGroupsOpen.value[group.label] !== false
}
function toggleGroup(group: NavGroup): void {
  // The active item's group stays expanded — don't let it be collapsed shut.
  if (groupHasActive(group)) return
  navGroupsOpen.value = { ...navGroupsOpen.value, [group.label]: !isGroupOpen(group) }
}

onMounted(fetchMe)

watch(() => route.fullPath, () => {
  mobileNavOpen.value = false
  profileOpen.value = false
})

onKeyStroke('Escape', () => { if (profileOpen.value) profileOpen.value = false })

// Close the profile dropdown on any outside click. (A fixed inset-0 backdrop
// can't do this here: the header's backdrop-blur makes it a containing block
// for fixed descendants, so such a backdrop only spans the topbar strip.)
const profileWrap = ref<HTMLElement | null>(null)
onClickOutside(profileWrap, () => { profileOpen.value = false })

// One title for every page rendered under this layout.
useHead({ title: 'Team Workspace' })
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
        <div class="flex items-center gap-2.5">
          <!-- Mobile: open the slide-in drawer -->
          <button
            class="md:hidden inline-flex items-center justify-center size-8 rounded-md transition-colors hover:bg-(--color-bg-secondary)"
            :style="{ color: 'var(--color-text)' }"
            aria-label="Toggle navigation"
            @click="mobileNavOpen = !mobileNavOpen"
          >
            <UIcon :name="mobileNavOpen ? 'i-fluent-dismiss-24-regular' : 'i-fluent-line-horizontal-3-24-regular'" class="size-5" />
          </button>
          <!-- Desktop: collapse the sidebar to an icon-only rail -->
          <button
            class="hidden md:inline-flex items-center justify-center size-8 rounded-md transition-colors hover:bg-(--color-bg-secondary)"
            :style="{ color: 'var(--color-text)' }"
            :aria-pressed="sidebarCollapsed"
            :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
            @click="sidebarCollapsed = !sidebarCollapsed"
          >
            <UIcon name="i-fluent-line-horizontal-3-24-regular" class="size-5" />
          </button>
          <BrandMark to="/team" wordmark="Team Workspace" />
        </div>

        <div class="relative flex items-center gap-2">
          <!-- Light / dark toggle -->
          <button
            type="button"
            class="size-9 rounded-full inline-flex items-center justify-center border transition-colors hover:bg-(--color-bg-secondary)"
            :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)', color: 'var(--color-text-secondary)' }"
            aria-label="Toggle dark mode"
            @click="toggleDark"
          >
            <ClientOnly>
              <UIcon :name="colorMode.value === 'dark' ? 'i-fluent-weather-sunny-24-regular' : 'i-fluent-weather-moon-24-regular'" class="size-4" />
              <template #fallback>
                <span class="size-4 inline-block" />
              </template>
            </ClientOnly>
          </button>

          <div ref="profileWrap" class="relative">
          <button
            type="button"
            class="relative size-9 rounded-full inline-flex items-center justify-center border transition-colors hover:bg-(--color-bg-secondary)"
            :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)', color: 'var(--color-text-secondary)' }"
            :aria-expanded="profileOpen"
            aria-label="Account menu"
            @click="profileOpen = !profileOpen"
          >
            <UIcon name="i-lucide-user" class="size-4" />
            <!-- Availability dot (Profile page) — visible without opening the menu. -->
            <span
              v-if="availabilityMeta"
              class="absolute bottom-0 right-0 size-2.5 rounded-full border-2"
              :style="{ background: availabilityMeta.color, borderColor: 'var(--color-bg)' }"
              :aria-label="availabilityMeta.label"
              role="status"
            />
          </button>

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
                <div class="flex items-center gap-1.5 mt-2 flex-wrap justify-center">
                  <span
                    v-if="roleLabel"
                    class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded inline-flex items-center gap-1"
                    :style="{ color: 'var(--color-accent)', background: 'var(--color-accent-soft)' }"
                  >
                    <UIcon name="i-lucide-badge-check" class="size-3" />
                    {{ roleLabel }}
                  </span>
                  <span
                    v-if="availabilityMeta"
                    class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded inline-flex items-center gap-1"
                    :style="{ color: availabilityMeta.color, background: 'var(--color-bg-secondary)' }"
                  >
                    <span class="size-1.5 rounded-full" :style="{ background: availabilityMeta.color }" />
                    {{ availabilityMeta.label }}
                  </span>
                </div>
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
      </div>
    </header>

    <div class="flex-1 flex">
      <!-- Sidebar (desktop) — sticks below the topbar while content scrolls -->
      <aside
        class="hidden md:flex flex-col border-r self-start sticky"
        :style="{
          width: sidebarCollapsed ? '68px' : '248px',
          top: '3.5rem',
          height: 'calc(100vh - 3.5rem)',
          background: 'var(--color-bg)',
          borderColor: 'var(--color-border)',
        }"
      >
        <!-- min-h-0 lets the nav shrink below its content so overflow-y engages;
             without it the flexbox default (min-height: auto) cuts long menus off. -->
        <nav class="side-nav-scroll flex-1 min-h-0 p-3 flex flex-col gap-1.5 overflow-y-auto overflow-x-hidden">
          <!-- Collapsed rail: no room for labels, so flatten groups to icons with
               a hairline between them; each icon names itself via a hover
               tooltip (right side, teleported past the rail's overflow clip). -->
          <template v-if="sidebarCollapsed">
            <template v-for="(group, gi) in navGroups" :key="group.label">
              <hr v-if="gi > 0" class="my-1 border-0 border-t" :style="{ borderColor: 'var(--color-border)' }" >
              <UTooltip
                v-for="item in group.items"
                :key="item.to"
                :text="item.label"
                :content="{ side: 'right', sideOffset: 10 }"
                :delay-duration="150"
                :ui="{ content: 'admin-nav-tooltip' }"
              >
                <NuxtLink
                  :to="item.to"
                  class="admin-nav-item"
                  :style="{ justifyContent: 'center', paddingLeft: 0, paddingRight: 0, width: '100%' }"
                  :data-active="isAdminNavActive(item, route.path)"
                >
                  <UIcon :name="item.icon" class="size-4.5 shrink-0" />
                </NuxtLink>
              </UTooltip>
            </template>
          </template>

          <!-- Expanded: muted section label (also the collapse toggle) + items. -->
          <template v-else>
            <div v-for="group in navGroups" :key="group.label" class="flex flex-col gap-1">
              <button
                type="button"
                class="admin-nav-group-label"
                :aria-expanded="isGroupOpen(group)"
                @click="toggleGroup(group)"
              >
                <span>{{ group.label }}</span>
                <UIcon
                  name="i-lucide-chevron-down"
                  class="size-3.5 shrink-0 transition-transform"
                  :style="{ transform: isGroupOpen(group) ? 'rotate(0deg)' : 'rotate(-90deg)' }"
                />
              </button>
              <div v-show="isGroupOpen(group)" class="flex flex-col gap-1">
                <NuxtLink
                  v-for="item in group.items"
                  :key="item.to"
                  :to="item.to"
                  class="admin-nav-item"
                  :data-active="isAdminNavActive(item, route.path)"
                >
                  <UIcon :name="item.icon" class="size-4.5 shrink-0" />
                  <span>{{ item.label }}</span>
                </NuxtLink>
              </div>
            </div>
          </template>
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
          <nav class="p-3 flex flex-col gap-1.5 h-full overflow-y-auto">
            <div v-for="group in navGroups" :key="group.label" class="flex flex-col gap-1">
              <button
                type="button"
                class="admin-nav-group-label"
                :aria-expanded="isGroupOpen(group)"
                @click="toggleGroup(group)"
              >
                <span>{{ group.label }}</span>
                <UIcon
                  name="i-lucide-chevron-down"
                  class="size-3.5 shrink-0 transition-transform"
                  :style="{ transform: isGroupOpen(group) ? 'rotate(0deg)' : 'rotate(-90deg)' }"
                />
              </button>
              <div v-show="isGroupOpen(group)" class="flex flex-col gap-1">
                <NuxtLink
                  v-for="item in group.items"
                  :key="item.to"
                  :to="item.to"
                  class="admin-nav-item"
                  :data-active="isAdminNavActive(item, route.path)"
                >
                  <UIcon :name="item.icon" class="size-4.5 shrink-0" />
                  <span>{{ item.label }}</span>
                </NuxtLink>
              </div>
            </div>
            <hr class="my-2 border-0 border-t" :style="{ borderColor: 'var(--color-border)' }" >
            <button
              class="admin-nav-item"
              @click="logout"
            >
              <UIcon name="i-lucide-log-out" class="size-4.5 shrink-0" />
              <span>Sign out</span>
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

/* Sidebar scrolls independently once modules outgrow the viewport — slim,
   token-colored scrollbar so the rail stays quiet. */
.side-nav-scroll {
  scrollbar-width: thin;
  scrollbar-color: var(--color-border-strong) transparent;
}
.side-nav-scroll::-webkit-scrollbar { width: 6px; }
.side-nav-scroll::-webkit-scrollbar-thumb {
  background: var(--color-border-strong);
  border-radius: 9999px;
}
.side-nav-scroll::-webkit-scrollbar-track { background: transparent; }
</style>
