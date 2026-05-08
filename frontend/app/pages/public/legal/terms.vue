<script setup lang="ts">
definePageMeta({ layout: 'public' })

const prohibitions = [
  'Use the website for any unlawful purpose or in violation of applicable laws',
  'Reproduce, distribute, or create derivative works from our content without written permission',
  'Attempt to gain unauthorised access to any part of the website or its infrastructure',
  'Transmit any harmful, offensive, or disruptive content',
  'Impersonate Axel Nova Ventures, its team, or any other person or entity',
]

const ipItems = [
  'All written content, case studies, and editorial copy',
  'Design work, visual assets, and user interface elements',
  'Code samples and technical demonstrations presented on this website',
  'The axelnova brand name, logo, and associated visual identity',
]

useScrollReveal('.reveal')
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-24 pb-32">
    <div class="mb-16 reveal">
      <p class="eyebrow mb-3">Legal</p>
      <h1 class="text-[clamp(32px,4.5vw,48px)] font-semibold tracking-tight leading-tight mb-5">
        Terms & Conditions
      </h1>
      <div class="flex flex-wrap items-center gap-2.5 text-[13px]" style="color: var(--color-text-tertiary);">
        <span>Axel Nova Ventures</span>
        <span aria-hidden>·</span>
        <span>Last updated: May 2026</span>
        <span aria-hidden>·</span>
        <NuxtLink to="/legal/privacy-policy" style="color: var(--color-accent);">Privacy Policy</NuxtLink>
        <span aria-hidden>·</span>
        <NuxtLink to="/legal/disclaimer" style="color: var(--color-accent);">Disclaimer</NuxtLink>
      </div>
    </div>

    <div class="max-w-3xl space-y-12">
      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Acceptance of Terms</h2>
        <p class="text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          By accessing or using axelnova.tech, you agree to be bound by these Terms & Conditions
          and all applicable laws and regulations. If you do not agree with any part of these terms,
          you must not use this website. These terms apply to all visitors, users, and anyone who
          accesses or uses our website.
        </p>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Intellectual Property</h2>
        <p class="text-[16px] leading-relaxed mb-4" style="color: var(--color-text-secondary);">
          All content on this website is the intellectual property of
          <span style="color: var(--color-text);" class="font-medium">Axel Nova Ventures</span>
          unless otherwise stated. This includes:
        </p>
        <ul class="space-y-2.5 mb-5">
          <li
            v-for="item in ipItems"
            :key="item"
            class="flex items-start gap-3 text-[15px] leading-relaxed"
            style="color: var(--color-text-secondary);"
          >
            <span class="mt-2 w-1.5 h-1.5 rounded-full shrink-0" style="background: var(--color-accent);" />
            {{ item }}
          </li>
        </ul>
        <p class="text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          You may not reproduce, distribute, or otherwise use any content from this website for
          commercial purposes without express written permission from Axel Nova Ventures. Personal
          and educational use with proper attribution is permitted.
        </p>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Use of Website</h2>
        <p class="text-[16px] leading-relaxed mb-4" style="color: var(--color-text-secondary);">
          You agree to use this website only for lawful purposes. The following activities are
          strictly prohibited:
        </p>
        <ul class="space-y-2.5">
          <li
            v-for="item in prohibitions"
            :key="item"
            class="flex items-start gap-3 text-[15px] leading-relaxed"
            style="color: var(--color-text-secondary);"
          >
            <span class="mt-2 w-1.5 h-1.5 rounded-full shrink-0" style="background: var(--color-danger);" />
            {{ item }}
          </li>
        </ul>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Services & Engagements</h2>
        <p class="text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          This website serves as a portfolio and point of contact for professional services offered
          by Axel Nova Ventures. Any client engagement, project agreement, or service delivery is
          governed by a separate written agreement entered into between both parties. Nothing on
          this website constitutes a legally binding offer to provide services unless confirmed
          in a formal written agreement.
        </p>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Disclaimer of Warranties</h2>
        <p class="text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          This website is provided on an "as is" and "as available" basis. Axel Nova Ventures makes
          no warranties, express or implied, regarding the website's availability, accuracy,
          suitability for a particular purpose, or freedom from errors. We do not warrant that the
          website will be uninterrupted, secure, or free of viruses or other harmful components.
        </p>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Limitation of Liability</h2>
        <p class="text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          To the fullest extent permitted by law, Axel Nova Ventures shall not be liable for any
          direct, indirect, incidental, special, or consequential damages arising from your use of,
          or inability to use, this website or its content. This includes but is not limited to
          damages for loss of profits, data, or goodwill, even if we have been advised of the
          possibility of such damages.
        </p>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">External Links</h2>
        <p class="text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          This website may contain links to third-party websites for convenience or reference.
          These links do not constitute an endorsement of the content, products, or services on
          those websites. Axel Nova Ventures has no control over external sites and accepts no
          responsibility for their content, privacy practices, or availability.
        </p>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Governing Law</h2>
        <p class="text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          These Terms & Conditions are governed by and construed in accordance with the laws of
          Malaysia. Any disputes arising from or related to your use of this website shall be
          subject to the exclusive jurisdiction of the courts of Malaysia.
        </p>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Changes to These Terms</h2>
        <p class="text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          We reserve the right to modify these Terms & Conditions at any time. Changes will be
          posted on this page with a revised "last updated" date. Your continued use of the website
          after any changes constitutes acceptance of the updated terms.
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
