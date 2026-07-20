<script setup lang="ts">
import SectionHeader from '~/components/shared/SectionHeader.vue'

/**
 * The published-testimonial wall on the public home page. Reads the cached
 * /v1/testimonials feed — only reviews an admin published AND the client
 * consented to ever appear here. Renders nothing at all when the feed is
 * empty (no broken section). Quiet cards; the hero keeps the page's one
 * signature moment.
 */
interface Testimonial {
  attribution_name: string | null
  attribution_role: string | null
  project_label: string | null
  overall: number | null
  praise: string | null
}

const { data } = await useFetch<{ data: Testimonial[] }>(
  `${useApiBase()}/api/v1/testimonials`,
  { key: 'public-testimonials' },
)

const testimonials = computed(() =>
  (data.value?.data ?? []).filter(t => t.praise && t.attribution_name))
</script>

<template>
  <section v-if="testimonials.length" class="max-w-7xl mx-auto px-6 pb-32 reveal">
    <SectionHeader
      eyebrow="Client feedback"
      title="What clients say."
      subtitle="Straight from post-project reviews — published only with each client's permission."
    />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <figure
        v-for="(t, i) in testimonials"
        :key="`${t.attribution_name}-${i}`"
        class="reveal rounded-2xl border p-6 flex flex-col"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }"
      >
        <div v-if="t.overall != null" class="flex items-center gap-1.5 mb-4">
          <UIcon name="i-lucide-star" class="size-3.5" :style="{ color: 'var(--color-accent)' }" />
          <span class="font-mono text-[12px] font-medium tabular-nums" :style="{ color: 'var(--color-text-secondary)' }">
            {{ t.overall }}/5
          </span>
        </div>
        <blockquote class="text-[15px] leading-relaxed flex-1" :style="{ color: 'var(--color-text)' }">
          “{{ t.praise }}”
        </blockquote>
        <figcaption class="mt-5 pt-4 border-t" :style="{ borderColor: 'var(--color-border)' }">
          <p class="text-[13px] font-medium" :style="{ color: 'var(--color-text)' }">{{ t.attribution_name }}</p>
          <p v-if="t.attribution_role || t.project_label" class="text-[11px] mt-0.5" :style="{ color: 'var(--color-text-tertiary)' }">
            {{ [t.attribution_role, t.project_label].filter(Boolean).join(' · ') }}
          </p>
        </figcaption>
      </figure>
    </div>
  </section>
</template>
