<script setup lang="ts">
const collectItems = [
  'Name and email address',
  'Message content submitted via the contact form',
  'Any additional information you voluntarily provide',
]

const autoCollectItems = [
  'Pages visited and time spent on each page',
  'Browser type, operating system, and device type',
  'General geographic region (country or city level)',
  'Referral source (how you found the website)',
]

const useItems = [
  'Respond to your inquiries and service requests',
  'Improve the performance and content of the website',
  'Understand how visitors engage with the website through aggregated analytics',
  'Comply with legal obligations where required',
]

const thirdPartyServices = [
  {
    name: 'Vercel',
    description: 'Hosting and deployment infrastructure. Vercel may collect server-side request data such as IP addresses and request paths for performance and security purposes.',
  },
  {
    name: 'Cloudflare',
    description: 'Content delivery network, DNS management, and DDoS protection. Cloudflare processes network traffic through its global infrastructure.',
  },
  {
    name: 'Google Analytics',
    description: 'Website usage analytics. Data is anonymised where possible. You may opt out using the Google Analytics Opt-out Browser Add-on available from Google.',
  },
]

const rights = [
  'Access the personal data we hold about you',
  'Request correction of inaccurate or incomplete data',
  'Withdraw consent to the processing of your personal data at any time',
  'Request deletion of your personal data, subject to legal obligations',
  'Lodge a complaint with the relevant data protection authority',
]

useScrollReveal('.reveal')
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-24 pb-32">
    <div class="mb-16 reveal">
      <p class="eyebrow mb-3">Legal</p>
      <h1 class="text-[clamp(32px,4.5vw,48px)] font-semibold tracking-tight leading-tight mb-5">
        Privacy Policy
      </h1>
      <div class="flex flex-wrap items-center gap-2.5 text-[13px]" style="color: var(--color-text-tertiary);">
        <span>Axel Nova Ventures</span>
        <span aria-hidden>·</span>
        <span>Last updated: May 2026</span>
        <span aria-hidden>·</span>
        <NuxtLink to="/legal/terms" style="color: var(--color-accent);">Terms & Conditions</NuxtLink>
        <span aria-hidden>·</span>
        <NuxtLink to="/legal/cookies" style="color: var(--color-accent);">Cookie Policy</NuxtLink>
      </div>
    </div>

    <div class="max-w-3xl space-y-12">
      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Overview</h2>
        <div class="space-y-4 text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          <p>
            Axel Nova Ventures ("we", "us", "our") operates axelnova.tech. This Privacy Policy explains
            how we collect, use, and protect information about you when you visit our website or engage
            with our services.
          </p>
          <p>
            By using this website, you agree to the collection and use of information in accordance with
            this policy. We are committed to protecting your privacy in compliance with the
            <span style="color: var(--color-text);" class="font-medium">Personal Data Protection Act 2010 (PDPA), Malaysia</span>.
          </p>
        </div>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Information We Collect</h2>
        <p class="text-[16px] leading-relaxed mb-4" style="color: var(--color-text-secondary);">
          When you submit our contact form, we may collect:
        </p>
        <ul class="space-y-2.5 mb-6">
          <li
            v-for="item in collectItems"
            :key="item"
            class="flex items-start gap-3 text-[15px] leading-relaxed"
            style="color: var(--color-text-secondary);"
          >
            <span class="mt-2 w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: var(--color-accent);" />
            {{ item }}
          </li>
        </ul>
        <p class="text-[16px] leading-relaxed mb-4" style="color: var(--color-text-secondary);">
          We may also automatically collect technical and usage data through analytics services:
        </p>
        <ul class="space-y-2.5">
          <li
            v-for="item in autoCollectItems"
            :key="item"
            class="flex items-start gap-3 text-[15px] leading-relaxed"
            style="color: var(--color-text-secondary);"
          >
            <span class="mt-2 w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: var(--color-accent);" />
            {{ item }}
          </li>
        </ul>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">How We Use Your Information</h2>
        <p class="text-[16px] leading-relaxed mb-4" style="color: var(--color-text-secondary);">
          We use the information we collect to:
        </p>
        <ul class="space-y-2.5 mb-5">
          <li
            v-for="item in useItems"
            :key="item"
            class="flex items-start gap-3 text-[15px] leading-relaxed"
            style="color: var(--color-text-secondary);"
          >
            <span class="mt-2 w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: var(--color-accent);" />
            {{ item }}
          </li>
        </ul>
        <p class="text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          We do not sell, trade, or otherwise transfer your personal information to third parties
          without your consent, except as required by law or to operate our website infrastructure.
        </p>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Third-Party Services</h2>
        <p class="text-[16px] leading-relaxed mb-6" style="color: var(--color-text-secondary);">
          This website uses the following third-party services that may independently collect data
          as described in their respective privacy policies:
        </p>
        <div class="space-y-3">
          <div
            v-for="service in thirdPartyServices"
            :key="service.name"
            class="rounded-2xl border p-5"
            :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
          >
            <p class="text-[14px] font-semibold mb-1.5" style="color: var(--color-text);">{{ service.name }}</p>
            <p class="text-[14px] leading-relaxed" style="color: var(--color-text-secondary);">{{ service.description }}</p>
          </div>
        </div>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Cookies</h2>
        <p class="text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          We use cookies to improve your browsing experience and understand how visitors use our
          website. For full details on what cookies we use and how to control them, please refer to
          our
          <NuxtLink to="/legal/cookies" style="color: var(--color-accent);">Cookie Policy</NuxtLink>.
        </p>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Data Retention</h2>
        <p class="text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          Contact form submissions are retained for up to 12 months to allow us to respond to and
          follow up on inquiries. Analytics data is retained as per the respective service providers'
          data retention policies. You may request deletion of your personal data at any time by
          contacting us directly.
        </p>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Your Rights</h2>
        <p class="text-[16px] leading-relaxed mb-4" style="color: var(--color-text-secondary);">
          Under the Personal Data Protection Act 2010 (Malaysia), you have the right to:
        </p>
        <ul class="space-y-2.5 mb-5">
          <li
            v-for="right in rights"
            :key="right"
            class="flex items-start gap-3 text-[15px] leading-relaxed"
            style="color: var(--color-text-secondary);"
          >
            <span class="mt-2 w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: var(--color-accent);" />
            {{ right }}
          </li>
        </ul>
        <p class="text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          To exercise any of these rights, contact us at
          <a href="mailto:baihaqie@axelnova.tech" style="color: var(--color-accent);">baihaqie@axelnova.tech</a>.
          We will respond within 21 days.
        </p>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Data Security</h2>
        <p class="text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          We take reasonable steps to protect your personal data against unauthorised access, loss,
          or misuse. This includes SSL/HTTPS encryption on all pages and Cloudflare's security and
          DDoS mitigation services. However, no method of transmission over the internet is
          completely secure, and we cannot guarantee absolute security.
        </p>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Changes to This Policy</h2>
        <p class="text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          We may update this Privacy Policy from time to time. Any changes will be posted on this
          page with a revised "last updated" date. Continued use of the website after changes are
          posted constitutes your acceptance of the revised policy. We encourage you to review this
          page periodically.
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
