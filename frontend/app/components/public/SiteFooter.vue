<script setup lang="ts">
// Storefront footer, after apple.com: the link directory collapses to
// accordion rows below `md` and opens into columns from `md` up. Every link
// is in the SSR markup at every width — a collapsed row is hidden, not
// unrendered — so crawlers still reach every page the footer links.
const props = defineProps<{
  // The header's nav array (layouts/public.vue) — Explore follows it.
  links: { label: string, to: string }[]
}>()

const route = useRoute()

const socials = [
  { label: 'GitHub',    href: 'https://github.com/byhaqie31',           icon: 'i-lucide-github',   external: true },
  { label: 'LinkedIn',  href: 'https://linkedin.com/in/byhaqieyusri',   icon: 'i-lucide-linkedin', external: true },
  { label: 'Portfolio', href: 'https://baihaqie.com',                   icon: 'i-lucide-globe',    external: true },
  { label: 'Email',     href: 'mailto:baihaqie@axelnova.tech',          icon: 'i-lucide-mail',     external: false },
]

// "Services" section — the service categories managed in the admin, each
// linking to its /services/{slug} page. Links on every page are how crawlers
// find those pages (the /services tabs only link the active one). If the API
// is down the section falls back to a single "All services" link.
const { data: footerServices } = await useFetch(`${useApiBase()}/api/v1/services`, {
  key: 'footer-services',
  transform: (res: { data: { slug: string, name: string }[] }) => res.data.map(c => ({ label: c.name, to: `/services/${c.slug}` })),
  default: () => [] as { label: string, to: string }[],
})

type FooterLink = { label: string, to: string, external?: boolean }

const sections = computed<{ key: string, title: string, links: FooterLink[] }[]>(() => [
  { key: 'explore', title: 'Explore', links: props.links },
  {
    key: 'services',
    title: 'Services',
    links: footerServices.value.length ? footerServices.value : [{ label: 'All services', to: '/services' }],
  },
  {
    key: 'support',
    title: 'Support',
    links: [
      { label: 'Contact Us',       to: '/contact' },
      // DuitNow QR + OCBC account (pages/public/support.vue) — replaced Ko-fi.
      { label: 'Support Our Work', to: '/support' },
      // The Google Business Profile review link — public feedback where it counts.
      { label: 'Give Feedback',    to: 'https://share.google/E4dCt3twNxrFlVKRm', external: true },
      { label: 'Report an Issue',  to: 'mailto:baihaqie@axelnova.tech?subject=Issue%20Report%20—%20axelnovaventures.com' },
    ],
  },
  {
    key: 'legal',
    title: 'Legal',
    links: [
      { label: 'Privacy Policy',     to: '/legal/privacy-policy' },
      { label: 'Terms & Conditions', to: '/legal/terms' },
      { label: 'Cookie Policy',      to: '/legal/cookies' },
      { label: 'Disclaimer',         to: '/legal/disclaimer' },
      { label: 'Refund Policy',      to: '/legal/refund' },
    ],
  },
])

// Back to top — the brand mark in the bottom bar. Rides Lenis when it owns
// scrolling (a native smooth scroll would fight it), then hands keyboard focus
// to the header's brand link so a keyboard user lands where the eye does.
const motion = useMotion()
const toTop = () => {
  if (motion.lenis) motion.lenis.scrollTo(0, { duration: motion.reduced ? 0 : 1.4 })
  else window.scrollTo({ top: 0, behavior: motion.reduced ? 'auto' : 'smooth' })
  document.querySelector<HTMLElement>('header a')?.focus({ preventScroll: true })
}

// Rows toggle independently (as on apple.com): closing a row above the one
// just tapped would shift it out from under the finger.
const open = ref<Record<string, boolean>>({})
const toggle = (key: string) => { open.value[key] = !open.value[key] }

watch(() => route.fullPath, () => { open.value = {} })
</script>

<template>
  <!-- The home page ends in a full-bleed colour band that should meet the footer;
       every other page keeps the breathing room above it — unless it sets
       `definePageMeta({ footerGap: false })` and owns its bottom spacing
       (blog articles, which end on the CTA card). -->
  <footer class="relative" :class="{ 'mt-32': route.path !== '/' && route.meta.footerGap !== false }" :style="{ background: 'var(--footer-bg)' }">
    <div class="border-t" :style="{ borderColor: 'var(--color-border)' }">
      <div class="max-w-7xl mx-auto px-6 pt-4 md:pt-12 pb-8">

        <!-- Directory: accordion rows on phones, columns from md -->
        <nav aria-label="Footer" class="md:grid md:grid-cols-4 md:gap-x-8">
          <section
            v-for="s in sections"
            :key="s.key"
            class="border-b md:border-b-0"
            :style="{ borderColor: 'var(--color-border)' }"
          >
            <h2>
              <button
                type="button"
                class="md:hidden flex w-full min-h-11 items-center justify-between gap-4 text-left text-[14px]"
                style="color: var(--color-text);"
                :aria-expanded="!!open[s.key]"
                :aria-controls="`footer-section-${s.key}`"
                @click="toggle(s.key)"
              >
                {{ s.title }}
                <UIcon
                  name="i-lucide-chevron-down"
                  class="size-4 shrink-0 transition-transform duration-300 motion-reduce:transition-none"
                  :class="{ 'rotate-180': open[s.key] }"
                  style="color: var(--color-text-secondary);"
                />
              </button>
              <span class="hidden md:block text-[12px] font-semibold mb-3" style="color: var(--color-text);">{{ s.title }}</span>
            </h2>

            <div :id="`footer-section-${s.key}`" class="footer-panel" :class="{ 'is-open': open[s.key] }">
              <ul class="flex flex-col pb-3 pl-3 md:gap-2 md:pb-0 md:pl-0">
                <li v-for="l in s.links" :key="l.to">
                  <NuxtLink
                    :to="l.to"
                    :target="l.external ? '_blank' : undefined"
                    :rel="l.external ? 'noopener' : undefined"
                    class="inline-flex min-h-11 items-center text-[14px] md:min-h-0 md:text-[12px] transition-colors"
                    style="color: var(--color-text-secondary);"
                  >
                    <span class="link-underline">{{ l.label }}</span>
                  </NuxtLink>
                </li>
              </ul>
            </div>
          </section>
        </nav>

        <!-- Company identity: name set like the section titles, details like the
             copyright line; Back to top on its right -->
        <div class="mt-6 md:mt-10 mb-8 md:mb-10 flex items-center justify-between gap-6">
          <div class="min-w-0 text-[12px] leading-relaxed" style="color: var(--color-text-secondary);">
            <p class="mb-1 text-[14px] md:text-[12px] font-semibold tracking-tight" style="color: var(--color-text);">Axel Nova Ventures</p>
            <p>Registration No.: 202603119899 (CA0420977-U)</p>
            <p>Kuala Lumpur, Malaysia</p>
          </div>

          <!-- Back to top: the brand mark floating in a slowly turning aurora
               ring. On hover the mark flies up and an arrow rises in its
               place. Right of the company identity, at every width. -->
          <button
            type="button"
            class="footer-top shrink-0 flex flex-col items-center gap-1.5"
            title="Back to top"
            @click="toTop"
          >
            <span class="footer-top-badge">
              <span class="footer-top-ring" aria-hidden="true" />
              <span class="footer-top-core">
                <span class="footer-top-float">
                  <img src="/favicon/apple-touch-icon.png" alt="" class="footer-top-mark">
                </span>
                <UIcon name="i-lucide-arrow-up" class="footer-top-arrow size-4.5" />
              </span>
            </span>
            <span class="text-[11px] font-medium" style="color: var(--color-text-tertiary);">Back to top</span>
          </button>
        </div>

        <!-- Bottom bar -->
        <div class="border-t pt-6" :style="{ borderColor: 'var(--color-border)' }">
          <div class="flex flex-col items-center gap-3 text-center lg:grid lg:grid-cols-[1fr_auto_1fr] lg:items-center lg:gap-4 lg:text-left">
            <p class="text-[12px] order-1 lg:order-0" style="color: var(--color-text-secondary);">
              © 2026 Axel Nova Ventures. All rights reserved.
            </p>
            <div class="flex items-center gap-1 order-2 lg:order-0 lg:col-start-3 lg:justify-end">
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
              <NuxtLink
                to="/admin"
                aria-label="Admin access"
                title="Admin"
                class="footer-social-btn"
                :style="{ color: 'var(--color-text-tertiary)' }"
              >
                <UIcon name="i-lucide-settings" class="size-4" />
              </NuxtLink>
            </div>
          </div>
        </div>
      </div>
    </div>
  </footer>
</template>

<style scoped>
/* Accordion panel (below md): the 0fr → 1fr row animates the height without
   measuring it. `visibility` keeps a collapsed row's links out of the tab
   order; it flips after the close animation, before the open one. */
.footer-panel {
  display: grid;
  grid-template-rows: 0fr;
  visibility: hidden;
  transition: grid-template-rows 0.3s cubic-bezier(0.32, 0.72, 0, 1), visibility 0s linear 0.3s;
}
.footer-panel.is-open {
  grid-template-rows: 1fr;
  visibility: visible;
  transition: grid-template-rows 0.3s cubic-bezier(0.32, 0.72, 0, 1), visibility 0s;
}
.footer-panel > ul {
  min-height: 0;
  overflow: hidden;
}

/* md and up: always-open columns. Overflow back to visible so the last
   link's hover underline (bottom: -2px) isn't clipped. */
@media (min-width: 768px) {
  .footer-panel,
  .footer-panel.is-open {
    grid-template-rows: 1fr;
    visibility: visible;
    transition: none;
  }
  .footer-panel > ul { overflow: visible; }
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

/* Back to top. The ring is a conic gradient of the aurora stops turning
   behind a core disc (so it reads as a 2px rotating border); the mark floats
   on its own wrapper so the hover fly-up (a transform on the img) never
   fights the float animation. */
.footer-top-badge {
  position: relative;
  display: block;
  width: 48px;
  height: 48px;
  border-radius: 9999px;
  transition: transform 0.45s cubic-bezier(0.215, 0.61, 0.355, 1), box-shadow 0.45s ease;
}
.footer-top-ring {
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background: conic-gradient(
    from 0deg,
    var(--grad-aurora-blue),
    var(--grad-aurora-violet),
    var(--grad-aurora-pink),
    var(--grad-aurora-cyan),
    var(--grad-aurora-blue)
  );
  animation: footer-top-spin 8s linear infinite;
}
.footer-top-core {
  position: absolute;
  inset: 2px;
  display: grid;
  place-items: center;
  overflow: hidden;
  border-radius: inherit;
  background: var(--color-bg-elevated);
}
.footer-top-float {
  grid-area: 1 / 1;
  display: block;
  animation: footer-top-float 3.2s ease-in-out infinite;
}
.footer-top-mark {
  display: block;
  width: 26px;
  height: 26px;
  object-fit: contain;
  transition: transform 0.45s cubic-bezier(0.215, 0.61, 0.355, 1), opacity 0.3s ease;
}
.footer-top-arrow {
  grid-area: 1 / 1;
  color: var(--color-text);
  opacity: 0;
  transform: translateY(150%);
  transition: transform 0.45s cubic-bezier(0.215, 0.61, 0.355, 1), opacity 0.3s ease;
}
.footer-top { border-radius: 0.75rem; }
.footer-top:hover .footer-top-badge,
.footer-top:focus-visible .footer-top-badge {
  transform: translateY(-3px);
  box-shadow: var(--shadow-glow);
}
.footer-top:hover .footer-top-ring,
.footer-top:focus-visible .footer-top-ring { animation-duration: 2.5s; }
.footer-top:hover .footer-top-mark,
.footer-top:focus-visible .footer-top-mark { transform: translateY(-160%); opacity: 0; }
.footer-top:hover .footer-top-arrow,
.footer-top:focus-visible .footer-top-arrow { transform: translateY(0); opacity: 1; }

@keyframes footer-top-spin {
  to { transform: rotate(360deg); }
}
@keyframes footer-top-float {
  0%, 100% { transform: translateY(1.5px); }
  50% { transform: translateY(-1.5px); }
}

@media (prefers-reduced-motion: reduce) {
  .footer-panel,
  .footer-panel.is-open { transition: none; }
  .footer-top-ring,
  .footer-top-float { animation: none; }
  .footer-top-badge,
  .footer-top-mark,
  .footer-top-arrow { transition: none; }
}
</style>
