<script setup lang="ts">
definePageMeta({ layout: 'public' })

useScrollReveal('.reveal')
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-24 pb-32">
    <div class="mb-16 reveal">
      <p class="eyebrow mb-3">Legal</p>
      <h1 class="text-[clamp(32px,4.5vw,48px)] font-semibold tracking-tight leading-tight mb-5">
        Disclaimer
      </h1>
      <div class="flex flex-wrap items-center gap-2.5 text-[13px]" style="color: var(--color-text-tertiary);">
        <span>Axel Nova Ventures</span>
        <span aria-hidden>·</span>
        <span>Last updated: May 2026</span>
        <span aria-hidden>·</span>
        <NuxtLink to="/legal/terms" style="color: var(--color-accent);">Terms & Conditions</NuxtLink>
      </div>
    </div>

    <div class="max-w-3xl space-y-12">
      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">General</h2>
        <p class="text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          The information provided on axelnova.tech is for general informational and portfolio
          purposes only. While we make every effort to keep the content accurate, complete, and
          up to date, Axel Nova Ventures makes no representations or warranties of any kind,
          express or implied, about the completeness, accuracy, reliability, or suitability of
          the information, products, services, or related graphics on this website.
        </p>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">No Professional Advice</h2>
        <div class="space-y-4 text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          <p>
            Nothing on this website constitutes professional legal, financial, business, technical,
            or any other form of regulated advice. Content shared through case studies, articles,
            journal posts, or any other medium on this website is intended for informational and
            educational purposes only.
          </p>
          <p>
            Before making any business, legal, financial, or technical decisions based on
            information found on this website, you should consult with a qualified professional
            in the relevant field.
          </p>
        </div>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">AI & Technology Content</h2>
        <div class="space-y-4 text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          <p>
            This website may contain articles, essays, or commentary on topics including artificial
            intelligence, human-computer interaction, and emerging technology. These reflect the
            personal views and research perspectives of the author at the time of writing.
          </p>
          <p>
            Given the rapidly evolving nature of AI and technology, some content may become outdated
            quickly. We encourage readers to verify information through current primary sources.
            Any AI-assisted content on this website is reviewed and edited by the author before
            publication.
          </p>
        </div>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">External Links</h2>
        <p class="text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          This website may contain links to external websites that are not operated by Axel Nova
          Ventures. We have no control over the content, accuracy, or practices of those sites
          and accept no responsibility for them. The inclusion of any link does not imply
          endorsement of the linked website or its operator.
        </p>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Accuracy of Information</h2>
        <p class="text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          Project descriptions, case studies, and portfolio work shown on this website represent
          the author's involvement and contributions as accurately as possible. Certain details
          may be anonymised, generalised, or omitted for client confidentiality, non-disclosure
          obligations, or clarity of presentation.
        </p>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Website Availability</h2>
        <p class="text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          We strive to keep axelnova.tech available at all times, but we do not guarantee
          uninterrupted access. The website may be temporarily unavailable due to maintenance,
          technical issues, or circumstances beyond our control. We reserve the right to modify
          or discontinue any part of the website at any time without notice.
        </p>
      </section>

      <section class="reveal">
        <h2 class="text-[19px] font-semibold tracking-tight mb-5" style="color: var(--color-text);">Contact</h2>
        <p class="text-[16px] leading-relaxed mb-5" style="color: var(--color-text-secondary);">
          If you believe any content on this website is inaccurate, misleading, or raises a
          concern, please reach out to us directly.
        </p>
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
