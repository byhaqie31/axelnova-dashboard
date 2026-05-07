<script setup lang="ts">
const futureScope = [
  'Digital product purchases (templates, UI kits, design systems)',
  'SaaS subscription plans and seat licences',
  'Online consultations and coaching sessions',
  'Educational content and course access',
]

useScrollReveal('.reveal')
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-24 pb-32">
    <div class="mb-16 reveal">
      <p class="eyebrow mb-3">Legal</p>
      <h1 class="text-[clamp(32px,4.5vw,48px)] font-semibold tracking-tight leading-tight mb-5">
        Refund Policy
      </h1>
      <div class="flex flex-wrap items-center gap-2.5 text-[13px]" style="color: var(--color-text-tertiary);">
        <span>Axel Nova Ventures</span>
        <span aria-hidden>·</span>
        <NuxtLink to="/legal/terms" style="color: var(--color-accent);">Terms & Conditions</NuxtLink>
      </div>
    </div>

    <div class="max-w-3xl">
      <!-- Coming soon notice -->
      <div class="reveal mb-12">
        <div
          class="rounded-2xl border p-8 text-center"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
        >
          <div
            class="w-12 h-12 rounded-2xl flex items-center justify-center mx-auto mb-5"
            style="background: var(--color-accent-soft);"
          >
            <UIcon name="i-fluent-document-text-24-regular" class="size-6" style="color: var(--color-accent);" />
          </div>
          <h2 class="text-[20px] font-semibold tracking-tight mb-3" style="color: var(--color-text);">
            Policy Coming Soon
          </h2>
          <p class="text-[15px] leading-relaxed max-w-md mx-auto" style="color: var(--color-text-secondary);">
            Axel Nova Ventures is currently a services-based operation. A formal Refund Policy
            will be published when digital products or subscription services are launched.
          </p>
        </div>
      </div>

      <div class="space-y-12">
        <section class="reveal">
          <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Current Services</h2>
          <div class="space-y-4 text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
            <p>
              At present, Axel Nova Ventures operates as a professional services business offering
              bespoke design and engineering engagements. All service agreements, including payment
              terms, revision policy, cancellation, and delivery expectations, are documented in
              the individual client contract or proposal signed before work commences.
            </p>
            <p>
              If you have a concern about a service engagement, please contact us directly. We
              resolve disputes on a case-by-case basis and are committed to fair outcomes.
            </p>
          </div>
        </section>

        <section class="reveal">
          <h2 class="text-[19px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">Future Product Scope</h2>
          <p class="text-[16px] leading-relaxed mb-4" style="color: var(--color-text-secondary);">
            When introduced, this Refund Policy will cover:
          </p>
          <ul class="space-y-2.5">
            <li
              v-for="item in futureScope"
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
          <h2 class="text-[19px] font-semibold tracking-tight mb-5" style="color: var(--color-text);">Questions & Disputes</h2>
          <p class="text-[16px] leading-relaxed mb-5" style="color: var(--color-text-secondary);">
            For any billing, payment, or service concern, reach out and we will work to resolve
            it fairly and promptly.
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
  </div>
</template>
