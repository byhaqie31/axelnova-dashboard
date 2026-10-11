<script setup lang="ts">
import CollabLockup from '~/components/public/CollabLockup.vue'
import type { CollaborationPartner } from '~/data/collaborations'

// One studio partner as a full-width co-branded link card — the /collaborations
// index and the homepage section (CollabsLatest) share it. The named lockup IS
// the card's heading ("Axel Nova × {partner}"), so there's one × and no text
// title repeating the pair; `headingLevel` fits it to the page outline (h2 on
// /collaborations, h3 under the homepage section's h2). Wears the partner's
// scope class (`collab-{slug}`) so the card carries the co-brand.
//
// The parent wraps each card in its `reveal` element: GSAP leaves an inline
// transform on what it reveals, which would cancel this card's hover lift.
withDefaults(defineProps<{
  partner: CollaborationPartner
  headingLevel?: 'h2' | 'h3'
}>(), { headingLevel: 'h2' })
</script>

<template>
  <NuxtLink
    :to="`/collaborations/${partner.slug}`"
    :class="`collab-${partner.slug}`"
    class="collab-card group relative block rounded-3xl border p-7 sm:p-10 overflow-hidden"
    :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
  >
    <span aria-hidden="true" class="collab-card-wash" />
    <div class="relative grid md:grid-cols-[auto_1fr_auto] gap-8 md:gap-10 items-center">
      <!-- Centred on its own row on phones; the left column of the md row. -->
      <CollabLockup
        :as="headingLevel"
        named
        class="justify-self-center md:justify-self-start"
        :partner-name="partner.name"
        :partner-symbol="partner.symbol_url"
      />

      <div class="min-w-0">
        <p class="eyebrow mb-3">
          Collaboration<template v-if="partner.partner_since"> · Since {{ partner.partner_since.slice(0, 4) }}</template>
        </p>
        <p class="text-[16px] leading-relaxed max-w-2xl mb-5" style="color: var(--color-text-secondary);">
          {{ partner.summary }}
        </p>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="s in partner.services"
            :key="s"
            class="text-[12px] font-medium px-3 py-1 rounded-full"
            :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
          >{{ s }}</span>
        </div>
      </div>

      <span
        class="inline-flex items-center gap-1.5 text-[14px] font-medium whitespace-nowrap transition-all group-hover:gap-2.5"
        style="color: var(--color-accent);"
      >
        See the collaboration <span aria-hidden="true">→</span>
      </span>
    </div>
  </NuxtLink>
</template>

<style scoped>
.collab-card {
  transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
}
.collab-card:hover {
  transform: translateY(-2px);
  border-color: var(--color-border-strong) !important;
  box-shadow: var(--shadow-card-hover);
}
/* Co-brand wash — its own absolutely positioned child (UI-STANDARDS hard rule 8). */
.collab-card-wash {
  position: absolute;
  inset: 0;
  pointer-events: none;
  background: radial-gradient(70% 90% at 0% 0%, var(--color-accent-soft) 0%, transparent 60%);
  opacity: 0.8;
}
@media (prefers-reduced-motion: reduce) {
  .collab-card:hover { transform: none; }
}
</style>
