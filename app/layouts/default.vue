<script setup lang="ts">
import { onClickOutside } from '@vueuse/core'

const route = useRoute()
const colorMode = useColorMode()
const mobileOpen = ref(false)
const scrolled = ref(false)
const headerRef = ref<HTMLElement | null>(null)

const links = [
  { label: 'Home',     to: '/' },
  { label: 'About',    to: '/about' },
  { label: 'Company',  to: '/company' },
  { label: 'Projects', to: '/projects' },
  { label: 'Services', to: '/services' },
]

const isActive = (to: string) => to === '/' ? route.path === '/' : route.path.startsWith(to)

const toggleDark = () => {
  colorMode.preference = colorMode.value === 'dark' ? 'light' : 'dark'
}
const setMode = (m: 'light' | 'dark') => { colorMode.preference = m }

watch(() => route.fullPath, () => { mobileOpen.value = false })

onClickOutside(headerRef, () => { mobileOpen.value = false })

onMounted(() => {
  const onScroll = () => { scrolled.value = window.scrollY > 8 }
  onScroll()
  window.addEventListener('scroll', onScroll, { passive: true })
  onBeforeUnmount(() => window.removeEventListener('scroll', onScroll))
})
</script>

<template>
  <div class="min-h-screen flex flex-col" style="background: var(--color-bg); color: var(--color-text);">
    <!-- NAV -->
    <header ref="headerRef" class="sticky top-0 z-50">
      <!-- Iridescent gradient hairline (always visible on refresh) -->
      <div class="aurora-line" />

      <div
        class="backdrop-blur-xl border-b transition-colors duration-300"
        :style="{
          background: scrolled ? 'var(--nav-bg-scrolled)' : 'var(--nav-bg-top)',
          borderColor: 'var(--color-border)'
        }"
      >
        <nav class="max-w-7xl mx-auto h-12 px-6 flex items-center justify-between">
          <NuxtLink to="/" class="text-[15px] font-semibold tracking-tight inline-flex items-center gap-2">
            <span
              aria-hidden
              class="size-2 rounded-full"
              style="background: var(--grad-iridescent); box-shadow: 0 0 12px rgba(0,113,227,0.45);"
            />
            <span class="text-gradient">axelnova</span>
          </NuxtLink>

          <ul class="hidden md:flex items-center gap-8">
            <li v-for="l in links" :key="l.to">
              <NuxtLink
                :to="l.to"
                class="text-[12px] transition-colors"
                :style="{
                  color: isActive(l.to) ? 'var(--color-text)' : 'var(--color-text-secondary)',
                  fontWeight: isActive(l.to) ? 500 : 400
                }"
              >
                {{ l.label }}
              </NuxtLink>
            </li>
          </ul>

          <div class="flex items-center gap-1.5">
            <button
              class="hidden md:inline-flex items-center justify-center size-8 rounded-full transition-colors hover:bg-(--color-bg-secondary)"
              :style="{ color: 'var(--color-text-secondary)' }"
              aria-label="Toggle dark mode"
              @click="toggleDark"
            >
              <ClientOnly>
                <UIcon
                  :name="colorMode.value === 'dark' ? 'i-fluent-weather-sunny-24-regular' : 'i-fluent-weather-moon-24-regular'"
                  class="size-3.5"
                />
                <template #fallback>
                  <span class="size-3.5 inline-block" />
                </template>
              </ClientOnly>
            </button>

            <NuxtLink
              to="/services"
              class="hidden md:inline-flex btn-pill btn-pill-accent"
              style="height: 32px; font-size: 12px; padding: 0 16px;"
            >
              Let's talk
            </NuxtLink>

            <button
              class="md:hidden inline-flex items-center justify-center size-8 rounded-full"
              :style="{ color: 'var(--color-text)' }"
              aria-label="Toggle menu"
              :aria-expanded="mobileOpen"
              @click="mobileOpen = !mobileOpen"
            >
              <UIcon :name="mobileOpen ? 'i-fluent-dismiss-24-regular' : 'i-fluent-line-horizontal-3-24-regular'" class="size-4" />
            </button>
          </div>
        </nav>

        <Transition name="page">
          <div
            v-if="mobileOpen"
            class="md:hidden border-t"
            :style="{
              borderColor: 'var(--color-border)',
              background: 'var(--nav-mobile-bg)'
            }"
          >
            <!-- Top row: Appearance segmented control on the right -->
            <div
              class="px-6 pt-4 pb-3 flex items-center justify-between gap-3"
            >
              <p class="text-[11px] font-medium uppercase tracking-wide" style="color: var(--color-text-tertiary);">
                Appearance
              </p>

              <div
                class="inline-flex p-0.5 rounded-full"
                :style="{ background: 'var(--color-bg-secondary)', border: '1px solid var(--color-border)' }"
              >
                <ClientOnly>
                  <button
                    type="button"
                    class="inline-flex items-center justify-center gap-1.5 h-7 px-3 rounded-full text-[12px] font-medium transition-all"
                    :style="{
                      background: colorMode.value !== 'dark' ? 'var(--color-bg)' : 'transparent',
                      color: colorMode.value !== 'dark' ? 'var(--color-text)' : 'var(--color-text-secondary)',
                      boxShadow: colorMode.value !== 'dark' ? 'var(--shadow-xs)' : 'none'
                    }"
                    :aria-pressed="colorMode.value !== 'dark'"
                    aria-label="Light mode"
                    @click="setMode('light')"
                  >
                    <UIcon name="i-fluent-weather-sunny-24-regular" class="size-3.5" />
                    Light
                  </button>
                  <button
                    type="button"
                    class="inline-flex items-center justify-center gap-1.5 h-7 px-3 rounded-full text-[12px] font-medium transition-all"
                    :style="{
                      background: colorMode.value === 'dark' ? 'var(--color-bg)' : 'transparent',
                      color: colorMode.value === 'dark' ? 'var(--color-text)' : 'var(--color-text-secondary)',
                      boxShadow: colorMode.value === 'dark' ? 'var(--shadow-xs)' : 'none'
                    }"
                    :aria-pressed="colorMode.value === 'dark'"
                    aria-label="Dark mode"
                    @click="setMode('dark')"
                  >
                    <UIcon name="i-fluent-weather-moon-24-regular" class="size-3.5" />
                    Dark
                  </button>
                  <template #fallback>
                    <span class="inline-block h-7 w-35" />
                  </template>
                </ClientOnly>
              </div>
            </div>

            <!-- Divider -->
            <div class="border-t" :style="{ borderColor: 'var(--color-border)' }" />

            <!-- Page links -->
            <nav class="px-6 py-3 flex flex-col">
              <NuxtLink
                v-for="l in links" :key="l.to"
                :to="l.to"
                class="text-[16px] py-2.5 transition-colors"
                :style="{
                  color: isActive(l.to) ? 'var(--color-text)' : 'var(--color-text-secondary)',
                  fontWeight: isActive(l.to) ? 500 : 400
                }"
              >
                {{ l.label }}
              </NuxtLink>
            </nav>
          </div>
        </Transition>
      </div>
    </header>

    <main class="flex-1">
      <slot />
    </main>

    <footer class="mt-32 relative">
      <div class="aurora-line opacity-60" />
      <div class="border-t" :style="{ borderColor: 'var(--color-border)' }">
        <div class="max-w-7xl mx-auto px-6 py-5 flex flex-col md:flex-row items-center justify-between gap-3">
          <p class="text-[12px]" style="color: var(--color-text-secondary);">
            Ahmad Baihaqie · axelnova.tech © 2026
          </p>
          <div class="flex items-center gap-6 text-[12px] flex-wrap">
            <a href="https://baihaqie.com" target="_blank" rel="noopener" style="color: var(--color-text-secondary);">Portfolio</a>
            <a href="https://github.com/byhaqie31" target="_blank" rel="noopener" style="color: var(--color-text-secondary);">GitHub</a>
            <a href="https://linkedin.com/in/byhaqieyusri" target="_blank" rel="noopener" style="color: var(--color-text-secondary);">LinkedIn</a>
            <a href="mailto:byhaqie1455@gmail.com" style="color: var(--color-text-secondary);">Contact</a>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>
