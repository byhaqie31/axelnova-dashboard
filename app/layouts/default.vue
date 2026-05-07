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
  { label: 'Contact',  to: '/contact' },
]

const socials = [
  { label: 'GitHub',    href: 'https://github.com/byhaqie31',           icon: 'i-lucide-github',   external: true },
  { label: 'LinkedIn',  href: 'https://linkedin.com/in/byhaqieyusri',   icon: 'i-lucide-linkedin', external: true },
  { label: 'Portfolio', href: 'https://baihaqie.com',                   icon: 'i-lucide-globe',    external: true },
  { label: 'Email',     href: 'mailto:baihaqie@axelnova.tech',          icon: 'i-lucide-mail',     external: false },
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
        <div class="max-w-7xl mx-auto px-6 pt-12 pb-8">

          <!-- Main 5-column grid -->
          <div class="grid grid-cols-2 lg:grid-cols-[1.8fr_1fr_1fr_1fr_1fr] gap-x-8 gap-y-10 mb-10">

            <!-- Brand: full width on mobile, first col on desktop -->
            <div class="col-span-2 lg:col-span-1">
              <NuxtLink to="/" class="text-[15px] font-semibold tracking-tight inline-flex items-center gap-2 mb-4">
                <span
                  aria-hidden
                  class="size-2 rounded-full"
                  style="background: var(--grad-iridescent); box-shadow: 0 0 12px rgba(0,113,227,0.45);"
                />
                <span class="text-gradient">axelnova</span>
              </NuxtLink>
              <p class="text-[13px] leading-relaxed mb-5 max-w-xs" style="color: var(--color-text-secondary);">
                Building thoughtful digital experiences through design, systems, and technology.
              </p>

              <!-- SSM card -->
              <div
                class="inline-block rounded-xl border px-4 py-3 mb-5"
                :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-secondary)' }"
              >
                <p class="text-[12px] font-semibold tracking-tight mb-0.5" style="color: var(--color-text);">
                  Axel Nova Ventures
                </p>
                <p class="text-[11px] font-medium" style="color: var(--color-text-tertiary);">
                  Registration No.: 202603119899 (CA0420977-U)
                </p>
                <p class="text-[11px] mt-0.5" style="color: var(--color-text-tertiary);">
                  Kuala Lumpur, Malaysia
                </p>
              </div>

              <!-- Availability badge -->
              <div class="flex items-center gap-2">
                <span class="footer-avail-dot" aria-hidden />
                <span class="text-[12px] font-medium" style="color: var(--color-text-secondary);">
                  Available for selected collaborations
                </span>
              </div>
            </div>

            <!-- Explore -->
            <div>
              <p class="text-[11px] font-medium uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Explore</p>
              <div class="flex flex-col gap-2.5">
                <NuxtLink
                  v-for="l in links"
                  :key="l.to"
                  :to="l.to"
                  class="text-[13px] transition-colors w-fit"
                  style="color: var(--color-text-secondary);"
                >
                  {{ l.label }}
                </NuxtLink>
                <NuxtLink to="/contact" class="text-[13px] transition-colors w-fit" style="color: var(--color-text-secondary);">Contact</NuxtLink>
              </div>
            </div>

            <!-- Services -->
            <div>
              <p class="text-[11px] font-medium uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Services</p>
              <div class="flex flex-col gap-2.5">
                <NuxtLink to="/services" class="text-[13px] transition-colors w-fit" style="color: var(--color-text-secondary);">UI/UX Design</NuxtLink>
                <NuxtLink to="/services" class="text-[13px] transition-colors w-fit" style="color: var(--color-text-secondary);">Frontend Engineering</NuxtLink>
                <NuxtLink to="/services" class="text-[13px] transition-colors w-fit" style="color: var(--color-text-secondary);">Product Design</NuxtLink>
                <NuxtLink to="/services" class="text-[13px] transition-colors w-fit" style="color: var(--color-text-secondary);">System Architecture</NuxtLink>
                <NuxtLink to="/services" class="text-[13px] transition-colors w-fit" style="color: var(--color-text-secondary);">Consultation</NuxtLink>
              </div>
            </div>

            <!-- Support -->
            <div>
              <p class="text-[11px] font-medium uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Support</p>
              <div class="flex flex-col gap-2.5">
                <NuxtLink to="/contact" class="text-[13px] transition-colors w-fit" style="color: var(--color-text-secondary);">Contact Us</NuxtLink>
                <a href="https://ko-fi.com/axelnova" target="_blank" rel="noopener" class="text-[13px] transition-colors w-fit" style="color: var(--color-text-secondary);">Support Our Work</a>
                <a href="mailto:baihaqie@axelnova.tech?subject=Feedback%20—%20axelnova.tech" class="text-[13px] transition-colors w-fit" style="color: var(--color-text-secondary);">Give Feedback</a>
                <a href="mailto:baihaqie@axelnova.tech?subject=Issue%20Report%20—%20axelnova.tech" class="text-[13px] transition-colors w-fit" style="color: var(--color-text-secondary);">Report an Issue</a>
              </div>
            </div>

            <!-- Legal -->
            <div>
              <p class="text-[11px] font-medium uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Legal</p>
              <div class="flex flex-col gap-2.5">
                <NuxtLink to="/legal/privacy-policy" class="text-[13px] transition-colors w-fit" style="color: var(--color-text-secondary);">Privacy Policy</NuxtLink>
                <NuxtLink to="/legal/terms" class="text-[13px] transition-colors w-fit" style="color: var(--color-text-secondary);">Terms & Conditions</NuxtLink>
                <NuxtLink to="/legal/cookies" class="text-[13px] transition-colors w-fit" style="color: var(--color-text-secondary);">Cookie Policy</NuxtLink>
                <NuxtLink to="/legal/disclaimer" class="text-[13px] transition-colors w-fit" style="color: var(--color-text-secondary);">Disclaimer</NuxtLink>
                <NuxtLink to="/legal/refund" class="text-[13px] transition-colors w-fit" style="color: var(--color-text-secondary);">Refund Policy</NuxtLink>
              </div>
            </div>
          </div>

          <!-- Bottom bar -->
          <div class="border-t pt-6" :style="{ borderColor: 'var(--color-border)' }">
            <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-4">
              <p class="text-[12px]" style="color: var(--color-text-secondary);">
                © 2026 Axel Nova Ventures. All rights reserved.
              </p>
              <p class="text-[11px] text-center" style="color: var(--color-text-tertiary);">
                Designed & built by Qie · Nuxt · Tailwind CSS · TypeScript
              </p>
              <div class="flex items-center gap-1 justify-end">
                <a
                  v-for="social in socials"
                  :key="social.label"
                  :href="social.href"
                  :target="social.external ? '_blank' : undefined"
                  :rel="social.external ? 'noopener' : undefined"
                  :aria-label="social.label"
                  class="footer-social-btn"
                  :style="{ color: 'var(--color-text-secondary)' }"
                >
                  <UIcon :name="social.icon" class="size-4" />
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>

<style scoped>
.footer-avail-dot {
  width: 7px;
  height: 7px;
  border-radius: 9999px;
  background: var(--color-success);
  box-shadow: 0 0 0 0 rgba(48, 209, 88, 0.45);
  animation: avail-pulse 2.4s ease-in-out infinite;
  flex-shrink: 0;
}

@keyframes avail-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(48, 209, 88, 0.45); }
  50% { box-shadow: 0 0 0 5px rgba(48, 209, 88, 0); }
}

.footer-social-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 9999px;
  transition: background 0.15s ease, color 0.15s ease;
}

.footer-social-btn:hover {
  background: var(--color-bg-secondary);
  color: var(--color-text) !important;
}

@media (prefers-reduced-motion: reduce) {
  .footer-avail-dot { animation: none; }
}
</style>
