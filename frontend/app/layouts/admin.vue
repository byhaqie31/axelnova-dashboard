<script setup lang="ts">
import BrandMark from '~/components/shared/BrandMark.vue'
import { visibleAdminNav, isAdminNavActive, isGroupPinned, type NavGroup, type Role } from '~/data/adminNav'

useSeoMeta({ robots: 'noindex, nofollow' })

const mobileNavOpen = ref(false)
const profileOpen = ref(false)
const appsOpen = ref(false)

// Desktop sidebar collapse → icon-only rail. Cookie-backed so it's resolved during
// SSR and the rail doesn't flash from expanded → collapsed on reload.
const sidebarCollapsed = useCookie<boolean>('axn_admin_sidebar_collapsed', { default: () => false })

// Per-group open/closed state, cookie-backed (SSR-resolved, no open/closed flash
// on reload). A group is open unless it holds an explicit `false`; the group that
// owns the active route is always forced open regardless of the stored value.
const navGroupsOpen = useCookie<Record<string, boolean>>('axn_admin_nav_groups', { default: () => ({}) })

// One-time migration: Task 1 of the portal restructure renamed the "Business"
// group to "Workspace". Both cookies key on the group label, so the rename
// would silently orphan any stored "Business" value — carry it over once.
function migrateGroupLabel(cookie: Ref<Record<string, boolean>>) {
  if (!('Business' in cookie.value)) return
  const { Business, ...rest } = cookie.value
  cookie.value = 'Workspace' in rest ? rest : { ...rest, Workspace: Business }
}
migrateGroupLabel(navGroupsOpen)

const route = useRoute()
const { logout, apiFetch, jumpToTeam } = useAdminAuth()

interface Me { id: number, name: string, email: string, role?: Role }
const me = ref<Me | null>(null)

// Role stays undefined until Phase 0 adds it to `/admin/me`; visibleAdminNav is
// permissive meanwhile, so all seven groups render for the current founder.
const navGroups = computed<NavGroup[]>(() => visibleAdminNav(me.value?.role))

// The rail is customizable: Overview is mandatory, every other group can be
// pinned/unpinned from the launchpad. Unpinned groups live only in "View
// more". Cookie-backed like the other sidebar prefs (SSR-resolved, no flash).
const navPinned = useCookie<Record<string, boolean>>('axn_admin_nav_pinned', { default: () => ({}) })
migrateGroupLabel(navPinned)
const isPinned = (group: NavGroup) => isGroupPinned(group, navPinned.value)
function togglePin(group: NavGroup) {
  if (group.mandatory) return
  navPinned.value = { ...navPinned.value, [group.label]: !isPinned(group) }
}
const sidebarGroups = computed<NavGroup[]>(() => navGroups.value.filter(isPinned))

// The launcher button lights up when the active route lives in a group the
// rail doesn't show — the user still gets a "you are here" cue.
const unpinnedActive = computed(() =>
  navGroups.value.some(g => !isPinned(g) && g.items.some(item => isAdminNavActive(item, route.path))),
)

// "View more" swaps the rail for a launchpad view — every group (rail +
// overflow) rendered as tile grids on one wider bar — then back. Fixed inner
// widths keep content steady while the aside's width animates (overflow
// clips the rest).
const LAUNCHER_W = 464
const railWidth = computed(() => (sidebarCollapsed.value ? 68 : 248))
const asideWidth = computed(() => `${appsOpen.value ? LAUNCHER_W : railWidth.value}px`)

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
  appsOpen.value = false
})

onKeyStroke('Escape', () => {
  if (profileOpen.value) profileOpen.value = false
  if (appsOpen.value) appsOpen.value = false
})

// Close the profile dropdown on any outside click. (A fixed inset-0 backdrop
// can't do this here: the header's backdrop-blur makes it a containing block
// for fixed descendants, so such a backdrop only spans the topbar strip.)
const profileWrap = ref<HTMLElement | null>(null)
onClickOutside(profileWrap, () => { profileOpen.value = false })

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
          <BrandMark to="/admin" wordmark="Admin Portal" />
        </div>

        <div class="relative flex items-center gap-2">
          <!-- Quick jumps to the other portals. Team signs in directly via the
               token exchange (new tab, own token key — the admin session here
               stays intact); Partners is a different account type, so that jump
               still lands on its login. -->
          <nav class="hidden md:flex items-center gap-1.5" aria-label="Other portals">
            <button type="button" class="portal-jump" @click="jumpToTeam">
              <UIcon name="i-lucide-users-round" class="size-3.5 shrink-0" />
              <span>Team</span>
              <UIcon name="i-lucide-arrow-up-right" class="size-3 shrink-0 opacity-60" />
            </button>
            <NuxtLink to="/partners/login" target="_blank" rel="noopener" class="portal-jump">
              <UIcon name="i-lucide-handshake" class="size-3.5 shrink-0" />
              <span>Partners</span>
              <UIcon name="i-lucide-arrow-up-right" class="size-3 shrink-0 opacity-60" />
            </NuxtLink>
          </nav>

          <div ref="profileWrap" class="relative">
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
      </div>
    </header>

    <div class="flex-1 flex">
      <!-- Sidebar (desktop) — sticks below the topbar while content scrolls -->
      <aside
        class="apps-aside hidden md:flex flex-col border-r self-start sticky overflow-hidden z-30"
        :style="{
          width: asideWidth,
          top: '3.5rem',
          height: 'calc(100vh - 3.5rem)',
          background: 'var(--color-bg)',
          borderColor: 'var(--color-border)',
        }"
      >
        <!-- Rail mode. Fixed inner width so items hold steady while the aside's
             width animates. min-h-0 lets the nav shrink below its content so
             overflow-y engages (flexbox default min-height:auto cuts menus off). -->
        <nav
          v-if="!appsOpen"
          class="side-nav-scroll flex-1 min-h-0 p-3 flex flex-col gap-1.5 overflow-y-auto overflow-x-hidden shrink-0"
          :style="{ width: `${railWidth}px` }"
        >
          <!-- Collapsed rail: no room for labels, so flatten groups to icons with
               a hairline between them; each icon names itself via a hover
               tooltip (right side, teleported past the rail's overflow clip). -->
          <template v-if="sidebarCollapsed">
            <template v-for="(group, gi) in sidebarGroups" :key="group.label">
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
            <div v-for="group in sidebarGroups" :key="group.label" class="flex flex-col gap-1">
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

        <!-- Launcher mode: the whole bar becomes a launchpad — every group
             (rail + overflow) as a tile grid on one wide surface. -->
        <div
          v-else
          class="side-nav-scroll flex-1 min-h-0 overflow-y-auto p-4 shrink-0"
          :style="{ width: `${LAUNCHER_W}px` }"
          aria-label="All apps"
        >
          <div v-for="(group, gi) in navGroups" :key="group.label" :class="{ 'mt-5': gi > 0 }">
            <div class="flex items-center justify-between px-1 mb-2">
              <p class="text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
                {{ group.label }}
              </p>
              <button
                v-if="!group.mandatory"
                type="button"
                class="pin-btn"
                :data-pinned="isPinned(group)"
                :aria-pressed="isPinned(group)"
                :title="isPinned(group) ? 'Unpin from sidebar' : 'Pin to sidebar'"
                @click="togglePin(group)"
              >
                <UIcon :name="isPinned(group) ? 'i-lucide-pin' : 'i-lucide-pin-off'" class="size-3.5" />
              </button>
            </div>
            <div class="grid grid-cols-4 gap-1.5">
              <NuxtLink
                v-for="item in group.items"
                :key="item.to"
                :to="item.to"
                class="app-tile"
                :data-active="isAdminNavActive(item, route.path)"
              >
                <UIcon :name="item.icon" class="size-4.5 shrink-0" />
                <span class="text-[11px] font-medium leading-tight text-center">{{ item.label }}</span>
              </NuxtLink>
            </div>
          </div>
        </div>

        <!-- Pinned launcher trigger — the rail stays glanceable; everything
             else lives one click away. Lights up when the active route
             belongs to a group the rail doesn't show. -->
        <div class="shrink-0 p-3 pt-2 border-t" :style="{ borderColor: 'var(--color-border)' }">
          <UTooltip
            v-if="sidebarCollapsed && !appsOpen"
            text="View more"
            :content="{ side: 'right', sideOffset: 10 }"
            :delay-duration="150"
            :ui="{ content: 'admin-nav-tooltip' }"
          >
            <button
              type="button"
              class="admin-nav-item w-full"
              :style="{ justifyContent: 'center', paddingLeft: 0, paddingRight: 0 }"
              :data-active="unpinnedActive"
              :aria-expanded="appsOpen"
              aria-label="View more apps"
              @click="appsOpen = !appsOpen"
            >
              <UIcon name="i-lucide-layout-grid" class="size-4.5 shrink-0" />
            </button>
          </UTooltip>
          <button
            v-else
            type="button"
            class="admin-nav-item w-full"
            :data-active="unpinnedActive"
            :aria-expanded="appsOpen"
            @click="appsOpen = !appsOpen"
          >
            <UIcon name="i-lucide-layout-grid" class="size-4.5 shrink-0" />
            <span>View more</span>
            <UIcon
              name="i-lucide-chevron-right"
              class="size-3.5 shrink-0 ml-auto transition-transform"
              :style="{ transform: appsOpen ? 'rotate(180deg)' : 'rotate(0deg)' }"
            />
          </button>
        </div>
      </aside>

      <!-- Click-away for the widened sidebar (below the aside, above content) -->
      <div v-if="appsOpen" class="hidden md:block fixed inset-0 z-20 cursor-default" @click="appsOpen = false" />

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
            <button type="button" class="admin-nav-item w-full" @click="jumpToTeam">
              <UIcon name="i-lucide-users-round" class="size-4.5 shrink-0" />
              <span>Team Workspace</span>
              <UIcon name="i-lucide-arrow-up-right" class="size-3.5 shrink-0 ml-auto opacity-60" />
            </button>
            <NuxtLink to="/partners/login" target="_blank" rel="noopener" class="admin-nav-item">
              <UIcon name="i-lucide-handshake" class="size-4.5 shrink-0" />
              <span>Partner sign-in</span>
              <UIcon name="i-lucide-arrow-up-right" class="size-3.5 shrink-0 ml-auto opacity-60" />
            </NuxtLink>
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
/* Compact topbar pills jumping to the other portals' sign-in pages — same
   material as the profile button (elevated bg, hairline border). */
.portal-jump {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 32px;
  padding: 0 12px;
  border-radius: 9999px;
  border: 1px solid var(--color-border);
  background: var(--color-bg-elevated);
  color: var(--color-text-secondary);
  font-size: 12px;
  font-weight: 500;
  transition: color 0.15s ease, background 0.15s ease;
}
.portal-jump:hover {
  background: var(--color-bg-secondary);
  color: var(--color-text);
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
  .drawer-backdrop-enter-active,
  .drawer-backdrop-leave-active,
  .drawer-panel-enter-active,
  .drawer-panel-leave-active { transition: none; }
}

/* The aside grows/shrinks to reveal the more-apps column. */
.apps-aside {
  transition: width 0.28s cubic-bezier(0.32, 0.72, 0, 1);
}
@media (prefers-reduced-motion: reduce) {
  .apps-aside { transition: none; }
}

/* Apps-launcher tiles — icon over label, same resting/hover/active grammar
   as .admin-nav-item but in launchpad form. */
.app-tile {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 12px 6px 10px;
  border-radius: 12px;
  color: var(--color-text-secondary);
  transition: background 0.15s ease, color 0.15s ease;
}
.app-tile:hover {
  background: var(--color-bg-secondary);
  color: var(--color-text);
}
.app-tile[data-active="true"] {
  background: var(--color-accent-soft);
  color: var(--color-accent);
}

/* Pin toggle beside each customizable group label in the launchpad. */
.pin-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 8px;
  color: var(--color-text-tertiary);
  transition: color 0.15s ease, background 0.15s ease;
}
.pin-btn:hover {
  color: var(--color-text);
  background: var(--color-bg-secondary);
}
.pin-btn[data-pinned="true"] {
  color: var(--color-accent);
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
