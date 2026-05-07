<script setup lang="ts">
const cookieTypes = [
  {
    name: 'Essential Cookies',
    description: 'Required for the website to function correctly. These include session handling, colour mode preference (light/dark), and navigation state. They cannot be disabled without affecting core functionality.',
    canDisable: false,
  },
  {
    name: 'Analytics Cookies',
    description: 'Help us understand how visitors interact with the website. We use anonymised data to identify popular pages, referral sources, and general usage patterns. These are set by Google Analytics and Vercel Analytics.',
    canDisable: true,
  },
  {
    name: 'Preference Cookies',
    description: 'Remember your settings between visits, such as your colour mode preference. These improve your experience by avoiding the need to re-apply settings each time you visit.',
    canDisable: true,
  },
]

const thirdParty = [
  {
    provider: 'Google Analytics',
    purpose: 'Aggregated website traffic and usage analytics',
    optOut: 'https://tools.google.com/dlpage/gaoptout',
  },
  {
    provider: 'Vercel Analytics',
    purpose: 'Performance monitoring and deployment analytics',
    optOut: null,
  },
  {
    provider: 'Cloudflare',
    purpose: 'Security, CDN performance, and bot detection',
    optOut: null,
  },
]

useScrollReveal('.reveal')
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-24 pb-32">
    <div class="mb-16 reveal">
      <p class="eyebrow mb-3">Legal</p>
      <h1 class="text-[clamp(32px,4.5vw,48px)] font-semibold tracking-tight leading-tight mb-5">
        Cookie Policy
      </h1>
      <div class="flex flex-wrap items-center gap-2.5 text-[13px]" style="color: var(--color-text-tertiary);">
        <span>Axel Nova Ventures</span>
        <span aria-hidden>·</span>
        <span>Last updated: May 2026</span>
        <span aria-hidden>·</span>
        <NuxtLink to="/legal/privacy-policy" style="color: var(--color-accent);">Privacy Policy</NuxtLink>
      </div>
    </div>

    <div class="max-w-3xl space-y-12">
      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">What Are Cookies</h2>
        <div class="space-y-4 text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          <p>
            Cookies are small text files placed on your device when you visit a website. They allow
            the website to remember information about your visit, such as your preferences and
            settings, making your next visit easier and the website more useful to you.
          </p>
          <p>
            Cookies can be "session" cookies (deleted when you close your browser) or "persistent"
            cookies (stored on your device until they expire or you delete them). We use both types
            depending on their purpose.
          </p>
        </div>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-6" style="color: var(--color-text);">Cookies We Use</h2>
        <div class="space-y-4">
          <div
            v-for="cookie in cookieTypes"
            :key="cookie.name"
            class="rounded-2xl border p-5"
            :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
          >
            <div class="flex items-center justify-between gap-4 mb-2.5">
              <p class="text-[15px] font-semibold" style="color: var(--color-text);">{{ cookie.name }}</p>
              <span
                class="text-[11px] font-medium px-2.5 py-1 rounded-full flex-shrink-0"
                :style="cookie.canDisable
                  ? { color: 'var(--color-accent)', background: 'var(--color-accent-soft)' }
                  : { color: 'var(--color-text-tertiary)', background: 'var(--color-bg-secondary)', border: '1px solid var(--color-border)' }
                "
              >
                {{ cookie.canDisable ? 'Optional' : 'Required' }}
              </span>
            </div>
            <p class="text-[14px] leading-relaxed" style="color: var(--color-text-secondary);">{{ cookie.description }}</p>
          </div>
        </div>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-6" style="color: var(--color-text);">Third-Party Cookies</h2>
        <p class="text-[16px] leading-relaxed mb-6" style="color: var(--color-text-secondary);">
          Some cookies are set by third-party services we use to operate and improve this website.
          These services have their own cookie and privacy policies:
        </p>
        <div class="overflow-hidden rounded-2xl border" :style="{ borderColor: 'var(--color-border)' }">
          <div
            v-for="(service, i) in thirdParty"
            :key="service.provider"
            class="px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-6"
            :class="i < thirdParty.length - 1 ? 'border-b' : ''"
            :style="{ borderColor: 'var(--color-border)', background: i % 2 === 0 ? 'var(--color-bg-elevated)' : 'var(--color-bg-secondary)' }"
          >
            <p class="text-[14px] font-semibold w-36 flex-shrink-0" style="color: var(--color-text);">{{ service.provider }}</p>
            <p class="text-[14px] flex-1" style="color: var(--color-text-secondary);">{{ service.purpose }}</p>
            <a
              v-if="service.optOut"
              :href="service.optOut"
              target="_blank"
              rel="noopener"
              class="text-[13px] flex-shrink-0"
              style="color: var(--color-accent);"
            >
              Opt out →
            </a>
          </div>
        </div>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Managing Your Cookie Preferences</h2>
        <div class="space-y-4 text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          <p>
            You can control and manage cookies through your browser settings. Most browsers allow
            you to view, delete, and block cookies from specific websites or all websites.
          </p>
          <p>
            Please note that disabling certain cookies may affect the functionality of this website.
            Essential cookies cannot be disabled without impacting your experience.
          </p>
          <p>
            For more information on how to manage cookies in your specific browser, visit your
            browser's help documentation or
            <a href="https://www.aboutcookies.org" target="_blank" rel="noopener" style="color: var(--color-accent);">aboutcookies.org</a>.
          </p>
        </div>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Changes to This Policy</h2>
        <p class="text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          We may update this Cookie Policy from time to time as we add new features or third-party
          services. Changes will be posted on this page with a revised "last updated" date.
        </p>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-5" style="color: var(--color-text);">Contact</h2>
        <div
          class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
        >
          <p class="text-[15px] font-semibold mb-1" style="color: var(--color-text);">Axel Nova Ventures</p>
          <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">Kuala Lumpur, Malaysia</p>
          <a href="mailto:baihaqie@axelnova.tech" class="text-[14px] mt-3 inline-block" style="color: var(--color-accent);">
            baihaqie@axelnova.tech
          </a>
        </div>
      </section>
    </div>
  </div>
</template>
