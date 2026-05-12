<script setup lang="ts">
definePageMeta({ layout: false })

useSeoMeta({ robots: 'noindex, nofollow' })

const route = useRoute()
const slug = computed(() => route.params.slug as string)

const proposal = computed(() => {
  if (slug.value !== 'demo') return null

  return {
    client: 'Acme Industries',
    preparedDate: '2026-05-06',
    status: 'Pending review',
    scope: [
      'Custom dashboard UI for the operations team (8 modules).',
      'Single sign-on integration with the existing identity provider.',
      'Reporting export (CSV / PDF) wired to internal API.',
      'Role-based access control across 3 user tiers.',
    ],
    timeline: [
      { phase: 'Discovery & Figma',         weeks: 1 },
      { phase: 'Design system + key flows', weeks: 1.5 },
      { phase: 'Frontend build',            weeks: 3 },
      { phase: 'Integration & QA',          weeks: 1.5 },
      { phase: 'Handover + walkthrough',    weeks: 0.5 },
    ],
    lineItems: [
      { name: 'Discovery + UX flows',     amount: 1800 },
      { name: 'Design system (Figma)',    amount: 2200 },
      { name: 'Frontend implementation',  amount: 6800 },
      { name: 'API integration & QA',     amount: 1800 },
      { name: 'Handover + documentation', amount: 600 },
    ],
  }
})

const total = computed(() => {
  if (!proposal.value) return 0
  return proposal.value.lineItems.reduce((sum, l) => sum + l.amount, 0)
})

useHead({ title: () => proposal.value ? `Proposal — ${proposal.value.client}` : 'Proposal not found' })
</script>

<template>
  <div class="min-h-screen" style="background: var(--color-bg); color: var(--color-text);">
    <header class="border-b" :style="{ borderColor: 'var(--color-border)' }">
      <div class="max-w-3xl mx-auto px-6 h-11 flex items-center justify-between">
        <NuxtLink to="/" class="text-[15px] font-semibold tracking-tight">
          axelnova
        </NuxtLink>
        <span
          class="text-[11px] font-medium px-2.5 py-1 rounded-full border"
          :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }"
        >
          Confidential
        </span>
      </div>
    </header>

    <div class="max-w-3xl mx-auto px-6 py-24">
      <div v-if="proposal">
        <div class="mb-16">
          <p class="text-[13px] font-medium mb-4" style="color: var(--color-accent);">
            Proposal · {{ proposal.preparedDate }}
          </p>
          <h1 class="text-5xl md:text-6xl font-semibold tracking-tight mb-5">
            For {{ proposal.client }}
          </h1>
          <span
            class="text-[12px] font-medium px-3 py-1 rounded-full inline-block"
            style="background: rgba(6,182,212,0.12); color: var(--color-accent);"
          >{{ proposal.status }}</span>
        </div>

        <section class="mb-16">
          <h2 class="text-2xl font-semibold tracking-tight mb-6">Project scope</h2>
          <ul class="space-y-3">
            <li v-for="(s, i) in proposal.scope" :key="i" class="flex items-start gap-3 text-[16px] leading-relaxed">
              <span class="text-[13px] font-medium mt-1.5 w-6" style="color: var(--color-accent);">{{ String(i + 1).padStart(2, '0') }}</span>
              <span>{{ s }}</span>
            </li>
          </ul>
        </section>

        <section class="mb-16">
          <h2 class="text-2xl font-semibold tracking-tight mb-6">Timeline</h2>
          <div class="border rounded-2xl overflow-hidden" :style="{ borderColor: 'var(--color-border)' }">
            <div
              v-for="(t, i) in proposal.timeline" :key="t.phase"
              class="flex items-center justify-between px-6 py-4"
              :style="{ borderTop: i === 0 ? 'none' : '1px solid var(--color-border)' }"
            >
              <span class="text-[15px]">{{ t.phase }}</span>
              <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ t.weeks }} {{ t.weeks === 1 ? 'week' : 'weeks' }}</span>
            </div>
          </div>
        </section>

        <section class="mb-16">
          <h2 class="text-2xl font-semibold tracking-tight mb-6">Investment</h2>
          <div class="border rounded-2xl overflow-hidden" :style="{ borderColor: 'var(--color-border)' }">
            <div
              v-for="(l, i) in proposal.lineItems" :key="l.name"
              class="flex items-center justify-between px-6 py-4 text-[15px]"
              :style="{ borderTop: i === 0 ? 'none' : '1px solid var(--color-border)' }"
            >
              <span>{{ l.name }}</span>
              <span>RM {{ l.amount.toLocaleString() }}</span>
            </div>
            <div
              class="flex items-center justify-between px-6 py-6 border-t"
              :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-secondary)' }"
            >
              <span class="text-[13px] font-medium" style="color: var(--color-text-secondary);">Total</span>
              <span class="text-3xl font-semibold tracking-tight">RM {{ total.toLocaleString() }}</span>
            </div>
          </div>
        </section>

        <footer class="border-t pt-8" :style="{ borderColor: 'var(--color-border)' }">
          <p class="text-[13px]" style="color: var(--color-text-secondary);">
            Prepared by Ahmad Baihaqie · axelnovaventures.com ·
            <a href="mailto:baihaqie@axelnova.tech" style="color: var(--color-accent);">baihaqie@axelnova.tech</a>
          </p>
        </footer>
      </div>

      <div v-else class="text-center py-20">
        <p class="text-5xl font-semibold tracking-tight mb-4">Proposal not found.</p>
        <p class="text-[15px] mb-8" style="color: var(--color-text-secondary);">
          This link may have expired or been mistyped.
        </p>
        <NuxtLink to="/" class="text-[14px]" style="color: var(--color-accent);">
          ← Back to axelnovaventures.com
        </NuxtLink>
      </div>
    </div>
  </div>
</template>
