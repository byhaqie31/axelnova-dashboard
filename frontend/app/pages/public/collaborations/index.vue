<script setup lang="ts">
definePageMeta({ layout: 'public' })

import SectionHeader from '~/components/shared/SectionHeader.vue'
import CollabPartnerCard from '~/components/public/CollabPartnerCard.vue'
import CollabModes from '~/components/public/CollabModes.vue'
import CollabInquiryForm from '~/components/public/CollabInquiryForm.vue'
import type { CollaborationPartner } from '~/data/collaborations'

// The studios Axel Nova builds with — NOT the referral programme (/partners).
// Each partner card (CollabPartnerCard, shared with the homepage section)
// links to its own page — only /collaborations/trlabs exists so far; a generic
// [slug] template comes with the second partner.
usePublicSeo({
  title: 'Studio Collaborations | Axel Nova Ventures',
  description: 'Axel Nova Ventures works with trusted studios in Malaysia to co-build, subcontract and refer projects. Meet our partners, or apply to collaborate.',
  path: '/collaborations',
  card: {
    label: 'COLLABORATIONS',
    headline: 'Studios we build with.',
    subline: 'Co-builds, subcontracts and referrals.',
  },
})

const { data } = await useFetch<{ data: CollaborationPartner[] }>(
  `${useApiBase()}/api/v1/collaborations`,
  { key: 'collaborations' },
)
const partners = computed(() => data.value?.data ?? [])

useScrollReveal('.reveal')
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-24 pb-32">
    <SectionHeader
      as="h1"
      eyebrow="Collaborations"
      title="Studios we build with."
      subtitle="Some projects need more skills than one studio carries. Axel Nova Ventures works with a small circle of trusted studios, so you get one accountable team without gaps."
    />

    <!-- Partners -->
    <section class="mb-32 space-y-5">
      <!-- `reveal` sits on the wrapper: GSAP leaves an inline transform on the
           element it reveals, which would cancel the card's hover lift. -->
      <div v-for="p in partners" :key="p.slug" class="reveal">
        <CollabPartnerCard :partner="p" />
      </div>
    </section>

    <!-- How we collaborate -->
    <section class="mb-32 reveal">
      <SectionHeader
        eyebrow="How it works"
        title="Three ways we work with partners."
        subtitle="Every collaboration starts from the project, not the org chart. We pick the arrangement that gets you the right people."
      />
      <CollabModes />
    </section>

    <!-- Partner with us -->
    <section id="apply" class="scroll-mt-28 reveal">
      <div class="grid lg:grid-cols-[1fr_1.4fr] gap-10 lg:gap-16">
        <div>
          <p class="eyebrow mb-3">Partner with us</p>
          <h2 class="text-3xl md:text-4xl font-semibold tracking-tight mb-5" style="color: var(--color-text);">
            Run a studio? Let's build together.
          </h2>
          <p class="text-[17px] leading-relaxed mb-8" style="color: var(--color-text-secondary);">
            If you design or build for clients and want a partner for the parts you don't cover, tell me
            about your work. Good partners share a standard: clear scope, honest timelines and work that lasts.
          </p>
          <div
            class="rounded-xl border px-4 py-3.5 flex items-start gap-3"
            :style="{ background: 'var(--color-bg-secondary)', borderColor: 'var(--color-border)' }"
          >
            <UIcon name="i-lucide-info" class="size-4 mt-0.5 shrink-0" style="color: var(--color-text-tertiary);" />
            <p class="text-[13px] leading-relaxed" style="color: var(--color-text-secondary);">
              Want to refer a client for a commission instead?
              <NuxtLink to="/partners" class="font-medium link-underline" style="color: var(--color-accent);">
                See the Partner Program
              </NuxtLink>
            </p>
          </div>
        </div>

        <CollabInquiryForm variant="collaboration" />
      </div>
    </section>
  </div>
</template>
