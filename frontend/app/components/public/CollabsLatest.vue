<script setup lang="ts">
import SectionHeader from '~/components/shared/SectionHeader.vue'
import CollabPartnerCard from '~/components/public/CollabPartnerCard.vue'
import type { CollaborationPartner } from '~/data/collaborations'

/**
 * The studio partners on the public home page, above the blog. Shares the
 * /collaborations index's fetch (same key, same payload), and renders nothing
 * at all when no partner is active (or the feed fails), same as BlogLatest.
 * See docs/global/COLLABORATIONS.md.
 */
const { data } = await useFetch<{ data: CollaborationPartner[] }>(
  `${useApiBase()}/api/v1/collaborations`,
  { key: 'collaborations' },
)
const partners = computed(() => data.value?.data ?? [])
</script>

<template>
  <section v-if="partners.length" class="max-w-7xl mx-auto px-6 pb-32 reveal">
    <SectionHeader
      eyebrow="Collaborations"
      title="Built with trusted studios."
      subtitle="When a project needs more than one studio, Axel Nova Ventures brings in partners it trusts. You still get one team and one point of contact."
      :action="{ label: 'All collaborations', to: '/collaborations' }"
    />

    <div class="space-y-5">
      <div v-for="p in partners" :key="p.slug" class="reveal">
        <CollabPartnerCard :partner="p" heading-level="h3" />
      </div>
    </div>
  </section>
</template>
